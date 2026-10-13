import { test, describe, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import { createScenario } from './helpers/scenario.mjs';
import { parseMarker } from '../lib/marker.mjs';

let s;
afterEach(() => s?.cleanup());

const markerOf = (fake, name) => {
  const t = fake.markerTags().find((x) => x.name === name);
  return t ? parseMarker('laravel-cart-release', t.message)?.marker : null;
};
const releaseOf = (fake, name) => fake.releases.find((r) => r.tag_name === name);
const dispatch = (sc, inputs) => sc.event('workflow_dispatch', { inputs });

describe('activation anchor', () => {
  test('before the anchor merges: no-op, prediction for #44, zero writes', async () => {
    s = createScenario();
    s.openAnchor();
    const r = await s.cli(['--dry-run', '--pr', '44']);
    assert.equal(r.code, 0);
    assert.match(r.out, /status=inactive/);
    assert.match(r.out, /#44 not merged; if merged now as major → 4\.0\.0 from 3\.2\.0/);
    // Events before the anchor (e.g. PR #43's merge) are no-ops.
    const ev = await s.event('pull_request_target', s.prClosedPayload(43));
    assert.equal(ev.code, 0);
    assert.match(ev.out, /not merged into main yet/);
    assert.deepEqual(s.fake.writes, []);
  });

  test('installation bootstrap: anchor released via workflow_run; pre-anchor PRs ignored; 3.1.5 release does not override 3.2.0', async () => {
    s = createScenario();
    const sha44 = s.mergeAnchor();
    const r = await s.event('workflow_run', s.fake.workflowRunPayload(sha44));
    assert.equal(r.code, 0, r.out);
    assert.match(r.out, /status=released/);
    assert.deepEqual(s.fake.tagNames(), ['3.1.5', '3.2.0', '4.0.0']);
    assert.equal(s.fake.refs.get('4.0.0').type, 'tag');
    const m = markerOf(s.fake, '4.0.0');
    assert.deepEqual([m.pr, m.sha, m.bump, m.version, m.previous], [44, sha44, 'major', '4.0.0', '3.2.0']);
    const tagObj = s.fake.tagObjects.get(s.fake.refs.get('4.0.0').sha);
    assert.equal(tagObj.object.sha, sha44, 'tag points at the exact merge commit');
    const rel = releaseOf(s.fake, '4.0.0');
    assert.deepEqual([rel.prerelease, rel.draft, rel.make_latest], [false, false, 'true']);
    assert.match(rel.body, /### Breaking changes/);
    assert.match(rel.body, /compare\/3\.2\.0\.\.\.4\.0\.0/);
    assert.doesNotMatch(rel.body, /Test plan/);
    assert.equal(releaseOf(s.fake, '3.2.0'), undefined, 'no 3.2.0 backfill');
    // dispatch for a pre-anchor PR reports it as unmanaged
    const d = await dispatch(s, { pr: '43', dry_run: true });
    assert.match(d.out, /merged before the activation anchor/);
  });
});

describe('PR eligibility', () => {
  test('merged PR (squash) is released with its classification', async () => {
    s = createScenario();
    s.mergeAnchor();
    const sha = s.merge(50, { title: 'feat(coupons): stacking' });
    await s.cli(['--execute']);
    assert.deepEqual(markerOf(s.fake, '4.1.0').sha, sha);
  });

  test('closed-unmerged PR is never released', async () => {
    s = createScenario();
    s.mergeAnchor();
    s.fake.addPull({ number: 51, title: 'feat: nope', state: 'closed' });
    const ev = await s.event('pull_request_target', s.prClosedPayload(51));
    assert.match(ev.out, /closed without merging/);
    const d = await s.cli(['--execute', '--pr', '51']);
    assert.match(d.out, /closed without merging; never released/);
    assert.deepEqual(s.fake.tagNames(), ['3.1.5', '3.2.0', '4.0.0']);
  });

  test('PR merged into another base is never released', async () => {
    s = createScenario();
    s.mergeAnchor();
    s.repo.branch('develop');
    const devSha = s.repo.commit('feat: on develop', 'develop');
    s.fake.addPull({ number: 52, title: 'feat: dev', base: 'develop', mergeSha: devSha });
    const ev = await s.event('pull_request_target', { action: 'closed', pull_request: { ...s.fake.pulls.get(52), merged: true } });
    assert.match(ev.out, /targets develop/);
    const d = await s.cli(['--execute', '--pr', '52']);
    assert.match(d.out, /targets acme\/cart:develop/);
    assert.ok(!s.fake.tagNames().includes('4.1.0'));
  });

  test('fork-origin PR merged into main is released (only the merge commit is used)', async () => {
    s = createScenario();
    s.mergeAnchor();
    const sha = s.merge(53, { title: 'fix: from a fork', headRepo: 'contributor/cart' });
    const r = await s.event('pull_request_target', { action: 'closed', pull_request: { ...s.fake.pulls.get(53), merged: true } });
    assert.equal(r.code, 0, r.out);
    assert.equal(markerOf(s.fake, '4.0.1').sha, sha);
  });

  test('non-PR commits on main (e.g. bot pushes) are skipped', async () => {
    s = createScenario();
    s.mergeAnchor();
    s.repo.commit('style: apply Pint fixes');
    const sha = s.merge(54, { title: 'docs: readme' });
    const r = await s.cli(['--execute']);
    assert.match(r.out, /skipped \(not a pull request merge commit\)/);
    assert.equal(markerOf(s.fake, '4.0.1').sha, sha);
  });
});
