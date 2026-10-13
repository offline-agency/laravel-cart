// Shared scenario: a tmp git repo + fake GitHub mirroring laravel-cart's
// pre-v4 state (3.1.5 lightweight tag with a release, 3.2.0 annotated tag
// without a release, PR #43 merged before the activation anchor PR #44).

import { mkdtempSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { run, loadConfig } from '../../cli.mjs';
import { createTmpRepo } from './tmp-git.mjs';
import { FakeGitHub } from './fake-github.mjs';

export const REPO = 'acme/cart';

export const ANCHOR = {
  title: 'v4.0.0: storage drivers, coupons, typed events',
  body: '# v4\n\nIntro.\n\n## Breaking changes\n\n- Laravel 10/11 dropped\n- `Buyable` is getter-only\n\n## Test plan\n\n- [x] tests\n',
};

export function createScenario() {
  const repo = createTmpRepo();
  const fake = new FakeGitHub({ repository: REPO, repo });
  const config = { ...loadConfig(), repository: REPO };

  const c315 = repo.commit('chore: release 3.1.5');
  fake.addTag('3.1.5', c315);
  fake.addRelease('3.1.5');
  const { sha: sha43 } = fake.mergePull(43, { title: 'feat: Laravel 13 support' });
  fake.setCi(sha43, 'success');
  fake.addTag('3.2.0', sha43, { message: 'Release 3.2.0' }); // annotated, no release, no marker

  const s = {
    repo,
    fake,
    config,
    sha43,
    out: [],
    openAnchor() {
      return fake.addPull({ number: 44, ...ANCHOR });
    },
    mergeAnchor({ ci = 'success', style = 'merge' } = {}) {
      const { sha } = fake.mergePull(44, ANCHOR, { style });
      if (ci) fake.setCi(sha, ci);
      return sha;
    },
    merge(number, fields, { ci = 'success', style = 'squash' } = {}) {
      const { sha } = fake.mergePull(number, fields, { style });
      if (ci) fake.setCi(sha, ci);
      return sha;
    },
    async cli(argv, env = {}) {
      const lines = [];
      const code = await run({
        argv,
        env: { GITHUB_REPOSITORY: REPO, ...env },
        cwd: repo.dir,
        transport: fake.transport,
        config,
        print: (l) => lines.push(l),
      });
      s.out.push(...lines);
      return { code, out: lines.join('\n') };
    },
    async event(eventName, payload, { ref = 'refs/heads/main', repository = REPO } = {}) {
      const dir = mkdtempSync(join(tmpdir(), 'release-event-'));
      const path = join(dir, 'event.json');
      writeFileSync(path, JSON.stringify(payload));
      return s.cli(['--event'], { GITHUB_EVENT_NAME: eventName, GITHUB_EVENT_PATH: path, GITHUB_REF: ref, GITHUB_REPOSITORY: repository });
    },
    prClosedPayload(number) {
      const pr = fake.pulls.get(number);
      return { action: 'closed', pull_request: { ...pr, merged: Boolean(pr.merged_at) } };
    },
    cleanup() {
      repo.cleanup();
    },
  };
  return s;
}
