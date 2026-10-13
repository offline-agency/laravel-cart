// Reconcile algorithm shared by every trigger (PR merge, CI completion,
// manual dispatch, local CLI). It derives everything from main's first-parent
// history plus tag markers, so it is idempotent and order-preserving no matter
// which event started it or in which order events arrived.

import { bumpVersion, compareVersions, parseVersion } from './semver.mjs';
import { classify } from './classify.mjs';
import { formatMarker, parseMarker } from './marker.mjs';
import { buildReleaseBody, buildTagMessage } from './notes.mjs';
import { ciStatus } from './ci.mjs';

class Stop extends Error {}

const oneLine = (v) => String(v ?? '').replace(/[\r\n]+/g, ' ').trim();

function isMergedInto(pr, branch, repository) {
  return Boolean(pr && pr.merged_at && pr.base?.ref === branch && pr.base?.repo?.full_name === repository && pr.merge_commit_sha);
}

async function loadTags(gh, config, warnings) {
  const pattern = new RegExp(config.tagPattern);
  const tags = [];
  for (const ref of await gh.listTagRefs()) {
    const name = ref.ref.replace(/^refs\/tags\//, '');
    if (!pattern.test(name) || !parseVersion(name)) continue;
    let { type, sha } = ref.object;
    let message = null;
    for (let depth = 0; type === 'tag' && depth < 5; depth++) {
      const obj = await gh.getTagObject(sha);
      if (message === null) message = obj.message ?? '';
      ({ type, sha } = obj.object);
    }
    if (type !== 'commit') continue;
    const tag = { name, commit: sha, annotated: message !== null, marker: null };
    const parsed = parseMarker(config.markerName, message);
    if (parsed && !parsed.ok) warnings.push(`tag ${name}: ${parsed.error}; treated as an unmarked tag`);
    if (parsed?.ok) {
      if (parsed.marker.version !== name) warnings.push(`tag ${name}: marker names version ${parsed.marker.version}; treated as unmarked`);
      else if (parsed.marker.sha !== sha) warnings.push(`tag ${name}: marker sha ${parsed.marker.sha} differs from tagged commit ${sha}; treated as unmarked`);
      else tag.marker = parsed.marker;
    }
    tags.push(tag);
  }
  return tags;
}

export async function reconcile({ gh, git, config, dryRun = true, targetPr = null, allowFetch = false }) {
  const branch = config.defaultBranch;
  const repository = gh.repository;
  const result = { status: 'noop', dryRun, entries: [], messages: [], warnings: [], writes: 0 };
  const entries = result.entries;

  // --- History -------------------------------------------------------------
  const headSha = await gh.branchSha(branch);
  if (!git.hasCommit(headSha) && allowFetch) git.fetchBranch(branch);
  if (!git.hasCommit(headSha)) {
    throw new Error(`${branch} HEAD ${headSha} is not in the local clone; run "git fetch origin ${branch}" (full history) and retry`);
  }
  const firstParent = git.firstParent(headSha).reverse(); // oldest -> newest
  const position = new Map(firstParent.map((sha, i) => [sha, i]));

  const tags = await loadTags(gh, config, result.warnings);
  const releases = new Map((await gh.listReleases()).map((r) => [r.tag_name, r]));

  // --- Helpers ---------------------------------------------------------------
  const ancestry = new Map();
  const isAncestor = (a, b) => {
    const key = `${a}..${b}`;
    if (!ancestry.has(key)) ancestry.set(key, a === b || git.isAncestor(a, b));
    return ancestry.get(key);
  };
  const tagsDesc = () => [...tags].sort((a, b) => compareVersions(b.name, a.name));
  const currentVersion = (sha) => tagsDesc().find((t) => isAncestor(t.commit, sha))?.name ?? null;
  const highestVersion = () => tagsDesc()[0]?.name ?? null;
  const markerByPr = new Map();
  const markerBySha = new Map();
  for (const t of tags.filter((x) => x.marker)) {
    for (const [map, key] of [[markerByPr, t.marker.pr], [markerBySha, t.marker.sha]]) {
      if (map.has(key)) {
        result.warnings.push(`tags ${map.get(key).name} and ${t.name} carry markers for the same ${typeof key === 'number' ? `PR #${key}` : `commit ${key}`}`);
      }
      map.set(key, t);
    }
  }

  function allocate(sha, bump) {
    const unmarked = tags.find((t) => t.commit === sha && !t.marker);
    if (unmarked) throw new Stop(`conflict: stable tag ${unmarked.name} already points at ${sha} without a release marker`);
    const current = currentVersion(sha);
    const next = bumpVersion(current ?? config.initialVersion ?? '0.0.0', bump);
    const taken = tags.find((t) => t.name === next);
    if (taken) {
      const owner = taken.marker ? `PR #${taken.marker.pr} @ ${taken.commit}` : `commit ${taken.commit}`;
      throw new Stop(`conflict: computed version ${next} is already taken by ${owner}`);
    }
    return { current, next };
  }

  async function findMergedPull(sha) {
    const match = (p) => p && p.merge_commit_sha === sha && p.merged_at && p.base?.ref === branch;
    let pr = (await gh.pullsForCommit(sha)).find(match);
    if (!pr) {
      findMergedPull.recent ??= await gh.recentClosedPulls(branch);
      pr = findMergedPull.recent.find(match);
    }
    return pr ?? null;
  }

  async function publish(entry, pr) {
    const marker = formatMarker(config.markerName, {
      pr: pr.number, sha: entry.sha, bump: entry.bump, reason: entry.reason, version: entry.next, previous: entry.current,
    });
    const tagObject = await gh.createTagObject({
      tag: entry.next,
      sha: entry.sha,
      message: buildTagMessage({ version: entry.next, pr, sha: entry.sha, bump: entry.bump, reason: entry.reason, marker }),
    });
    result.writes++;
    await gh.createRef(`refs/tags/${entry.next}`, tagObject.sha);
    result.writes++;
    const tag = { name: entry.next, commit: entry.sha, annotated: true, marker: { pr: pr.number, sha: entry.sha, version: entry.next } };
    tags.push(tag);
    markerByPr.set(pr.number, tag);
    markerBySha.set(entry.sha, tag);
    entry.tagCreated = true;
    await createRelease(entry, pr, marker);
  }

  async function createRelease(entry, pr, marker) {
    const isLatest = compareVersions(entry.next, highestVersion()) >= 0;
    await gh.createRelease({
      tag_name: entry.next,
      name: entry.next,
      body: buildReleaseBody({
        repository, version: entry.next, previous: entry.current, pr, sha: entry.sha, bump: entry.bump, reason: entry.reason, marker,
      }),
      draft: false,
      prerelease: false,
      make_latest: isLatest ? 'true' : 'false',
    });
    result.writes++;
    releases.set(entry.next, { tag_name: entry.next });
  }

  // --- Marked (already tagged) commit: released or tag-only recovery ---------
  async function handleMarked(tag) {
    const m = tag.marker;
    const entry = {
      pr: m.pr, sha: tag.commit, next: tag.name, current: m.previous ?? null, bump: m.bump, reason: m.reason, title: null,
    };
    if (releases.has(tag.name)) return { ...entry, state: 'released', detail: `tag ${tag.name} and its release exist` };
    const pr = await gh.getPull(m.pr);
    entry.title = pr?.title ?? null;
    if (!isMergedInto(pr, branch, repository) || pr.merge_commit_sha !== tag.commit) {
      return { ...entry, state: 'blocked', detail: `conflict: tag ${tag.name} marker names PR #${m.pr} @ ${tag.commit}, but the PR's merge commit is ${pr?.merge_commit_sha ?? 'unknown'}` };
    }
    if (dryRun) return { ...entry, state: 'tag-only', detail: `tag ${tag.name} exists without a release; a real run creates the release for this same tag` };
    const marker = formatMarker(config.markerName, { ...m, version: tag.name });
    try {
      await createRelease(entry, pr, marker);
    } catch (e) {
      return { ...entry, state: 'blocked', detail: `tag ${tag.name} exists but release creation failed: ${e.message}` };
    }
    return { ...entry, state: 'recovered', detail: `created the missing release for existing tag ${tag.name}` };
  }

  // --- Unreleased PR merge commit -------------------------------------------
  async function handlePull(number, sha) {
    const pr = await gh.getPull(number); // always re-query; never trust event payloads
    const entry = { pr: number, sha, title: pr?.title ?? null };
    if (!isMergedInto(pr, branch, repository) || pr.merge_commit_sha !== sha || !position.has(pr.merge_commit_sha)) {
      return { ...entry, state: 'blocked', detail: `PR #${number} re-query does not match merge commit ${sha} on ${branch} (merged=${Boolean(pr?.merged_at)}, base=${pr?.base?.repo?.full_name}:${pr?.base?.ref}, merge_commit_sha=${pr?.merge_commit_sha})` };
    }
    if (markerByPr.has(number)) {
      const t = markerByPr.get(number);
      return { ...entry, state: 'blocked', detail: `conflict: tag ${t.name} marks PR #${number} at ${t.commit}, but its merge commit is ${sha}` };
    }
    const cls = classify({
      title: pr.title, body: pr.body, labels: pr.labels, override: config.bumpOverrides?.[String(number)],
    });
    if (!cls.ok) return { ...entry, state: 'blocked', detail: `bump conflict: ${cls.conflict}` };
    Object.assign(entry, { bump: cls.bump, reason: cls.reason });
    try {
      Object.assign(entry, allocate(sha, cls.bump));
    } catch (e) {
      if (e instanceof Stop) return { ...entry, state: 'blocked', detail: e.message };
      throw e;
    }
    entry.ci = await ciStatus({ gh, config, sha });
    if (entry.ci.state === 'pending' || entry.ci.state === 'absent') {
      return { ...entry, state: 'pending', detail: `waiting for required check: ${entry.ci.detail}` };
    }
    if (entry.ci.state !== 'success') {
      return { ...entry, state: 'blocked', detail: `required check failed: ${entry.ci.detail}; re-run CI, then dispatch the Release workflow` };
    }
    if (dryRun) {
      // Simulate the allocation so later PRs in this dry run predict correctly.
      tags.push({ name: entry.next, commit: sha, annotated: true, marker: { pr: number, sha, version: entry.next } });
      return { ...entry, state: 'ready', detail: `dry run: would tag ${entry.next} at ${sha} and publish the release` };
    }
    try {
      await publish(entry, pr);
    } catch (e) {
      const where = entry.tagCreated ? `tag ${entry.next} created but release creation failed` : 'tag creation failed';
      return { ...entry, state: 'blocked', detail: `${where}: ${e.message}${entry.tagCreated ? ' (the next run creates the release for this same tag)' : ''}` };
    }
    return { ...entry, state: 'published', detail: `tagged ${entry.next} at ${sha} and published the release` };
  }

  // --- Prediction / explanation for a target PR not reached by the loop ------
  async function explain(number, anchorIndex) {
    const pr = await gh.getPull(number);
    if (!pr) return { pr: number, state: 'blocked', detail: `PR #${number} not found` };
    const base = { pr: number, title: pr.title, sha: pr.merge_commit_sha ?? null };
    if (pr.base?.ref !== branch || pr.base?.repo?.full_name !== repository) {
      return { ...base, state: 'ignored', detail: `PR #${number} targets ${pr.base?.repo?.full_name}:${pr.base?.ref}, not ${repository}:${branch}; not released` };
    }
    if (!pr.merged_at) {
      if (pr.state === 'closed') return { ...base, sha: null, state: 'ignored', detail: `PR #${number} was closed without merging; never released` };
      const cls = classify({ title: pr.title, body: pr.body, labels: pr.labels, override: config.bumpOverrides?.[String(number)] });
      if (!cls.ok) return { ...base, sha: null, state: 'predicted', detail: `#${number} not merged; bump conflict would block it: ${cls.conflict}` };
      const current = currentVersion(headSha);
      const next = bumpVersion(current ?? config.initialVersion ?? '0.0.0', cls.bump);
      return {
        ...base, sha: null, state: 'predicted', bump: cls.bump, reason: cls.reason, current, next,
        detail: `#${number} not merged; if merged now as ${cls.bump} → ${next} from ${current ?? 'nothing'} (${branch} HEAD ${headSha})`,
      };
    }
    const idx = position.get(pr.merge_commit_sha);
    if (idx === undefined) return { ...base, state: 'blocked', detail: `PR #${number} merge commit ${pr.merge_commit_sha} is not on ${branch} first-parent history` };
    if (anchorIndex === null || idx < anchorIndex) {
      return { ...base, state: 'ignored', detail: `PR #${number} was merged before the activation anchor (PR #${config.anchor.pr}); not managed by this workflow` };
    }
    return { ...base, state: 'queued', detail: `PR #${number} is queued behind an earlier unreleased PR (releases are strictly ordered)` };
  }

  // --- Activation anchor -----------------------------------------------------
  const anchorPull = await gh.getPull(config.anchor.pr);
  if (!isMergedInto(anchorPull, branch, repository)) {
    result.status = 'inactive';
    result.messages.push(`Activation anchor PR #${config.anchor.pr} is not merged into ${branch} yet: nothing to release (no-op).`);
    if (targetPr) entries.push(await explain(targetPr, null));
    return result;
  }
  const anchorIndex = position.get(anchorPull.merge_commit_sha);
  if (anchorIndex === undefined) {
    throw new Error(`anchor PR #${config.anchor.pr} merge commit ${anchorPull.merge_commit_sha} is not on ${branch} first-parent history`);
  }
  const window = firstParent.slice(anchorIndex);
  let frontier = -1;
  window.forEach((sha, i) => {
    if (markerBySha.has(sha)) frontier = i;
  });
  result.messages.push(
    `Anchor PR #${config.anchor.pr} @ ${anchorPull.merge_commit_sha}; ${window.length} first-parent commit(s) on ${branch} since (inclusive); ${branch} HEAD ${headSha}.`,
  );

  // --- Ordered walk ----------------------------------------------------------
  let reachedTarget = false;
  for (let i = 0; i < window.length; i++) {
    const sha = window[i];
    let entry;
    if (markerBySha.has(sha)) {
      entry = await handleMarked(markerBySha.get(sha));
    } else if (i < frontier) {
      continue; // inside already-released range and not a release: a non-PR commit
    } else {
      const pr = await findMergedPull(sha);
      if (!pr) {
        entries.push({ pr: null, sha, state: 'skipped', detail: 'not a pull request merge commit' });
        continue;
      }
      entry = await handlePull(pr.number, sha);
    }
    entries.push(entry);
    if (targetPr && entry.pr === targetPr) reachedTarget = true;
    if (entry.state === 'pending' || entry.state === 'blocked' || reachedTarget) break;
  }
  if (targetPr && !reachedTarget) entries.push(await explain(targetPr, anchorIndex));

  // --- Outcome -----------------------------------------------------------------
  const states = new Set(entries.map((e) => e.state));
  if (states.has('blocked')) result.status = 'blocked';
  else if (states.has('pending')) result.status = 'waiting';
  else if (result.writes > 0) result.status = 'released';
  else if (states.has('ready') || states.has('tag-only')) result.status = 'dry-run';
  else result.status = 'noop';
  return result;
}

export function formatReport(result) {
  const lines = [];
  lines.push(`Release reconcile (${result.dryRun ? 'DRY RUN, no writes' : 'live'}): status=${result.status}`);
  for (const m of result.messages) lines.push(oneLine(m));
  for (const w of result.warnings) lines.push(`warning: ${oneLine(w)}`);
  for (const e of result.entries) {
    if (e.state === 'skipped') {
      lines.push(`- commit ${e.sha}: skipped (${oneLine(e.detail)})`);
      continue;
    }
    lines.push(`- PR #${e.pr}${e.title ? ` "${oneLine(e.title)}"` : ''}`);
    lines.push(`    state:           ${e.state}`);
    lines.push(`    merge sha:       ${e.sha ?? '(not merged)'}`);
    if (e.bump) {
      lines.push(`    current version: ${e.current ?? '(none)'}`);
      lines.push(`    bump:            ${e.bump} (${oneLine(e.reason)})`);
      lines.push(`    next tag:        ${e.next}`);
    }
    if (e.ci) lines.push(`    CI passed:       ${e.ci.state}${e.ci.conclusion ? `/${e.ci.conclusion}` : ''} (${oneLine(e.ci.detail)})`);
    lines.push(`    detail:          ${oneLine(e.detail)}`);
  }
  if (result.entries.length === 0) lines.push('Nothing to do.');
  return lines;
}
