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

describe('classification at release time', () => {
  test('major / minor / patch(docs) / fallback in sequence', async () => {
    s = createScenario();
    s.mergeAnchor();
    s.merge(60, { title: 'feat: a' });
    s.merge(61, { title: 'docs: b' });
    s.merge(62, { title: 'Update README.md' });
    s.merge(63, { title: 'refactor!: c' });
    const r = await s.cli(['--execute']);
    assert.equal(r.code, 0, r.out);
    assert.deepEqual(s.fake.tagNames(), ['3.1.5', '3.2.0', '4.0.0', '4.1.0', '4.1.1', '4.1.2', '5.0.0']);
    assert.match(markerOf(s.fake, '4.1.2').reason, /fallback/);
  });

  test('label/title conflict blocks that PR and every later PR, then resumes after the label is fixed', async () => {
    s = createScenario();
    s.mergeAnchor();
    s.merge(64, { title: 'fix: x', labels: ['release:major'] });
    s.merge(65, { title: 'fix: y' });
    const r = await s.cli(['--execute']);
    assert.equal(r.code, 1);
    assert.match(r.out, /bump conflict/);
    assert.deepEqual(s.fake.tagNames(), ['3.1.5', '3.2.0', '4.0.0']);
    s.fake.pulls.get(64).labels = [{ name: 'release:patch' }];
    const again = await dispatch(s, { dry_run: false });
    assert.equal(again.code, 0, again.out);
    assert.deepEqual(s.fake.tagNames(), ['3.1.5', '3.2.0', '4.0.0', '4.0.1', '4.0.2']);
  });
});

describe('required check gate', () => {
  for (const outcome of ['failure', 'cancelled', 'skipped', 'timed_out', 'action_required', 'missing-check']) {
    test(`${outcome} blocks loudly with no writes`, async () => {
      s = createScenario();
      const sha = s.mergeAnchor({ ci: outcome });
      const r = await s.event('workflow_run', s.fake.workflowRunPayload(sha));
      assert.equal(r.code, 1, r.out);
      assert.match(r.out, /required check failed/);
      assert.deepEqual(s.fake.writes, []);
    });
  }

  for (const outcome of ['pending', 'absent']) {
    test(`${outcome} waits successfully with no writes, later PRs wait too`, async () => {
      s = createScenario();
      s.mergeAnchor({ ci: outcome });
      s.merge(70, { title: 'fix: later' });
      const r = await s.cli(['--execute']);
      assert.equal(r.code, 0, r.out);
      assert.match(r.out, /status=waiting/);
      assert.doesNotMatch(r.out, /PR #70/);
      assert.deepEqual(s.fake.writes, []);
    });
  }

  test('only the GitHub Actions "CI passed" check of the push run counts (decoys ignored)', async () => {
    s = createScenario();
    const sha = s.mergeAnchor({ ci: 'absent' }); // decoys succeed, real run absent
    const r = await s.cli(['--execute']);
    assert.match(r.out, /status=waiting/);
    s.fake.setCi(sha, 'success');
    assert.match((await s.cli(['--execute'])).out, /status=released/);
  });

  test('failed CI then successful rerun is recovered via dispatch', async () => {
    s = createScenario();
    const sha = s.mergeAnchor({ ci: 'failure' });
    assert.equal((await s.cli(['--execute'])).code, 1);
    s.fake.setCi(sha, 'success');
    const r = await dispatch(s, { pr: '44', dry_run: false });
    assert.equal(r.code, 0, r.out);
    assert.ok(s.fake.tagNames().includes('4.0.0'));
  });

  test('ciWaivers in config unblock a permanently red commit', async () => {
    s = createScenario();
    const sha = s.mergeAnchor({ ci: 'failure' });
    s.config.ciWaivers = { [sha]: 'CI infra outage, verified manually' };
    const r = await s.cli(['--execute']);
    assert.equal(r.code, 0, r.out);
    assert.ok(s.fake.tagNames().includes('4.0.0'));
  });
});
