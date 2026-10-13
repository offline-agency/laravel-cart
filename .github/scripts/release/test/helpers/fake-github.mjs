// In-memory fake of the GitHub REST endpoints used by the release scripts.
// It exposes a `transport(method, path, body)` compatible with lib/github.mjs
// and records every non-GET request in `writes`.

import { createHash } from 'node:crypto';

let seq = 1000;
const nextId = () => ++seq;
const fakeSha = (seed) => createHash('sha1').update(`${seed}:${nextId()}`).digest('hex');

export class FakeGitHub {
  constructor({ repository = 'acme/cart', repo }) {
    this.repository = repository;
    this.repo = repo; // tmp git repo: main HEAD comes from it
    this.pulls = new Map();
    this.refs = new Map(); // tag name -> {type, sha}
    this.tagObjects = new Map(); // sha -> {sha, tag, message, object}
    this.releases = [];
    this.workflowRuns = [];
    this.checkRuns = [];
    this.calls = [];
    this.writes = [];
    this.failures = { createRelease: 0, createRef: 0 };
    this.transport = this.transport.bind(this);
  }

  // ----- scenario builders ---------------------------------------------------
  addPull({ number, title, body = '', labels = [], base = 'main', baseRepo = this.repository, headRepo = this.repository, mergeSha = null, state = mergeSha ? 'closed' : 'open', merged = Boolean(mergeSha) }) {
    const pr = {
      number,
      title,
      body,
      state,
      html_url: `https://github.com/${this.repository}/pull/${number}`,
      labels: labels.map((name) => ({ name })),
      merged_at: merged ? new Date(Date.UTC(2026, 0, 1, 0, 0, number)).toISOString() : null,
      merge_commit_sha: mergeSha,
      base: { ref: base, repo: { full_name: baseRepo } },
      head: { ref: `feature-${number}`, repo: { full_name: headRepo } },
    };
    this.pulls.set(number, pr);
    return pr;
  }

  /** Merge a PR as a new commit on main of the tmp repo (squash style by default). */
  mergePull(number, fields, { style = 'squash' } = {}) {
    const sha = style === 'merge' ? this.repo.mergeCommit(`Merge PR #${number}`) : this.repo.commit(`${fields.title} (#${number})`);
    return { sha, pr: this.addPull({ number, ...fields, mergeSha: sha }) };
  }

  addTag(name, commit, { message = null } = {}) {
    if (message === null) {
      this.refs.set(name, { type: 'commit', sha: commit });
      return;
    }
    const sha = fakeSha(name);
    this.tagObjects.set(sha, { sha, tag: name, message, object: { type: 'commit', sha: commit } });
    this.refs.set(name, { type: 'tag', sha });
  }

  addRelease(tagName) {
    this.releases.push({ id: nextId(), tag_name: tagName, name: tagName, draft: false, prerelease: false });
  }

  /**
   * outcome: 'success' | 'failure' | 'cancelled' | 'skipped' | 'timed_out' | 'action_required'
   *        | 'pending' (run in progress) | 'absent' (no run) | 'missing-check' (run done, no aggregate)
   */
  setCi(sha, outcome) {
    this.workflowRuns = this.workflowRuns.filter((r) => r.head_sha !== sha);
    this.checkRuns = this.checkRuns.filter((c) => c.head_sha !== sha);
    // Decoys that must never count: a pull_request run and a third-party app check.
    const decoySuite = nextId();
    this.workflowRuns.push(this.#run(sha, { event: 'pull_request', suite: decoySuite, status: 'completed', conclusion: 'success' }));
    this.checkRuns.push(this.#check(sha, { suite: decoySuite, status: 'completed', conclusion: 'success' }));
    this.checkRuns.push(this.#check(sha, { suite: nextId(), status: 'completed', conclusion: 'success', app: 'evil-app' }));
    if (outcome === 'absent') return;
    const suite = nextId();
    const done = outcome !== 'pending';
    const conclusion = outcome === 'missing-check' ? 'failure' : done ? outcome : null;
    this.workflowRuns.push(this.#run(sha, { event: 'push', suite, status: done ? 'completed' : 'in_progress', conclusion }));
    if (outcome !== 'missing-check') {
      this.checkRuns.push(this.#check(sha, { suite, status: done ? 'completed' : 'in_progress', conclusion }));
    }
  }

  #run(sha, { event, suite, status, conclusion }) {
    return {
      id: nextId(),
      name: 'CI',
      path: '.github/workflows/ci.yml',
      head_sha: sha,
      head_branch: 'main',
      event,
      status,
      conclusion,
      run_attempt: 1,
      check_suite_id: suite,
      head_repository: { full_name: this.repository },
      repository: { full_name: this.repository },
    };
  }

  #check(sha, { suite, status, conclusion, app = 'github-actions' }) {
    return { id: nextId(), name: 'CI passed', head_sha: sha, status, conclusion, app: { slug: app }, check_suite: { id: suite } };
  }

  /** Workflow-run payload for the CI push run of `sha`. */
  workflowRunPayload(sha, overrides = {}) {
    const run = this.workflowRuns.find((r) => r.head_sha === sha && r.event === 'push') ?? this.#run(sha, { event: 'push', suite: 1, status: 'completed', conclusion: 'success' });
    return { action: 'completed', workflow_run: { ...run, ...overrides } };
  }

  tagNames() {
    return [...this.refs.keys()].sort();
  }

  markerTags() {
    return [...this.refs.entries()]
      .filter(([, ref]) => ref.type === 'tag')
      .map(([name, ref]) => ({ name, ...this.tagObjects.get(ref.sha) }));
  }

  // ----- transport -----------------------------------------------------------
  async transport(method, path, body) {
    this.calls.push({ method, path, body });
    if (method !== 'GET') this.writes.push({ method, path, body });
    const url = new URL(`https://fake${path}`);
    const q = url.searchParams;
    const prefix = `/repos/${this.repository}`;
    if (!url.pathname.startsWith(`${prefix}/`)) return { status: 404, data: { message: 'Not Found' } };
    const p = url.pathname.slice(prefix.length);
    const page = (items) => {
      const per = Number(q.get('per_page') ?? 30);
      const n = Number(q.get('page') ?? 1);
      return items.slice((n - 1) * per, n * per);
    };
    const ok = (data, status = 200) => ({ status, data: structuredClone(data) });
    let m;

    if (method === 'GET' && (m = /^\/pulls\/(\d+)$/.exec(p))) {
      const pr = this.pulls.get(Number(m[1]));
      return pr ? ok(pr) : { status: 404, data: { message: 'Not Found' } };
    }
    if (method === 'GET' && p === '/pulls') {
      const list = [...this.pulls.values()].filter((pr) => pr.state === q.get('state') && pr.base.ref === q.get('base'));
      return ok(page(list.reverse()));
    }
    if (method === 'GET' && (m = /^\/commits\/([0-9a-f]{40})\/pulls$/.exec(p))) {
      return ok([...this.pulls.values()].filter((pr) => pr.merge_commit_sha === m[1]));
    }
    if (method === 'GET' && (m = /^\/branches\/([^/]+)$/.exec(p))) {
      return ok({ name: m[1], commit: { sha: this.repo.head(decodeURIComponent(m[1])) } });
    }
    if (method === 'GET' && p === '/git/matching-refs/tags') {
      const refs = [...this.refs.entries()].map(([name, o]) => ({ ref: `refs/tags/${name}`, object: { ...o } }));
      return ok(page(refs));
    }
    if (method === 'GET' && (m = /^\/git\/tags\/([0-9a-f]{40})$/.exec(p))) {
      const t = this.tagObjects.get(m[1]);
      return t ? ok(t) : { status: 404, data: { message: 'Not Found' } };
    }
    if (method === 'POST' && p === '/git/tags') {
      const sha = fakeSha(body.tag);
      const obj = { sha, tag: body.tag, message: body.message, object: { type: body.type, sha: body.object } };
      this.tagObjects.set(sha, obj);
      return ok(obj, 201);
    }
    if (method === 'POST' && p === '/git/refs') {
      if (this.failures.createRef > 0) {
        this.failures.createRef--;
        return { status: 500, data: { message: 'injected ref failure' } };
      }
      const name = body.ref.replace(/^refs\/tags\//, '');
      if (this.refs.has(name)) return { status: 422, data: { message: 'Reference already exists' } };
      const type = this.tagObjects.has(body.sha) ? 'tag' : 'commit';
      this.refs.set(name, { type, sha: body.sha });
      return ok({ ref: body.ref, object: { type, sha: body.sha } }, 201);
    }
    if (method === 'GET' && p === '/releases') {
      return ok(page([...this.releases].reverse()));
    }
    if (method === 'POST' && p === '/releases') {
      if (this.failures.createRelease > 0) {
        this.failures.createRelease--;
        return { status: 502, data: { message: 'injected release failure' } };
      }
      if (!this.refs.has(body.tag_name)) return { status: 422, data: { message: 'tag does not exist' } };
      if (this.releases.some((r) => r.tag_name === body.tag_name)) return { status: 422, data: { message: 'already_exists' } };
      const rel = { id: nextId(), ...body };
      this.releases.push(rel);
      return ok(rel, 201);
    }
    if (method === 'GET' && (m = /^\/actions\/workflows\/([^/]+)\/runs$/.exec(p))) {
      const runs = this.workflowRuns.filter(
        (r) => r.path.endsWith(`/${decodeURIComponent(m[1])}`) && (!q.get('head_sha') || r.head_sha === q.get('head_sha')) && (!q.get('event') || r.event === q.get('event')),
      );
      return ok({ total_count: runs.length, workflow_runs: runs });
    }
    if (method === 'GET' && (m = /^\/commits\/([0-9a-f]{40})\/check-runs$/.exec(p))) {
      const runs = this.checkRuns.filter((c) => c.head_sha === m[1] && (!q.get('check_name') || c.name === q.get('check_name')));
      return ok({ total_count: runs.length, check_runs: runs });
    }
    return { status: 404, data: { message: `fake: no route for ${method} ${p}` } };
  }
}
