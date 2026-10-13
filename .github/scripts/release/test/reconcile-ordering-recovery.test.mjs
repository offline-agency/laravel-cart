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

describe('ordering, idempotency and recovery', () => {
  test('three rapid merges with out-of-order events release in history order, no loss or duplicates', async () => {
    s = createScenario();
    const a = s.mergeAnchor({ ci: 'pending' });
    const p1 = s.merge(101, { title: 'fix: one' }, { ci: 'pending' });
    const p2 = s.merge(102, { title: 'feat: two' }, { ci: 'pending' });
    const p3 = s.merge(103, { title: 'fix: three' }, { ci: 'pending' });

    // Events arrive: merge #103, CI #103 done, merge #101, CI anchor done, merge #102, CI #102, CI #101.
    assert.equal((await s.event('pull_request_target', s.prClosedPayload(103))).code, 0);
    s.fake.setCi(p3, 'success');
    await s.event('workflow_run', s.fake.workflowRunPayload(p3));
    await s.event('pull_request_target', s.prClosedPayload(101));
    assert.deepEqual(s.fake.writes, [], 'nothing released while the anchor is pending');
    s.fake.setCi(a, 'success');
    await s.event('workflow_run', s.fake.workflowRunPayload(a));
    assert.deepEqual(s.fake.tagNames(), ['3.1.5', '3.2.0', '4.0.0'], '#101 still pending');
    await s.event('pull_request_target', s.prClosedPayload(102));
    s.fake.setCi(p2, 'success');
    await s.event('workflow_run', s.fake.workflowRunPayload(p2));
    assert.deepEqual(s.fake.tagNames(), ['3.1.5', '3.2.0', '4.0.0'], '#102 must wait for #101');
    s.fake.setCi(p1, 'success');
    const last = await s.event('workflow_run', s.fake.workflowRunPayload(p1));
    assert.equal(last.code, 0, last.out);

    const expected = [['4.0.0', 44, a], ['4.0.1', 101, p1], ['4.1.0', 102, p2], ['4.1.1', 103, p3]];
    for (const [version, pr, sha] of expected) {
      const m = markerOf(s.fake, version);
      assert.deepEqual([m.pr, m.sha], [pr, sha], version);
      assert.ok(releaseOf(s.fake, version), `release ${version}`);
    }
    assert.equal(s.fake.releases.length, 1 + expected.length); // 3.1.5 + four new
    assert.equal(s.fake.markerTags().filter((t) => t.message.includes('laravel-cart-release')).length, 4);

    // Completed rerun: no-op.
    const before = s.fake.writes.length;
    const again = await s.event('workflow_run', s.fake.workflowRunPayload(p1));
    assert.match(again.out, /status=noop/);
    assert.equal(s.fake.writes.length, before);
  });

  test('tag created but release creation failed: next run creates the release for the same tag', async () => {
    s = createScenario();
    const sha = s.mergeAnchor();
    s.fake.failures.createRelease = 1;
    const first = await s.cli(['--execute']);
    assert.equal(first.code, 1);
    assert.match(first.out, /tag 4\.0\.0 created but release creation failed/);
    assert.ok(s.fake.tagNames().includes('4.0.0'));
    assert.equal(releaseOf(s.fake, '4.0.0'), undefined);

    const dry = await s.cli(['--dry-run']);
    assert.match(dry.out, /state:\s+tag-only/);

    const writesBefore = s.fake.writes.length;
    const second = await s.cli(['--execute']);
    assert.equal(second.code, 0, second.out);
    assert.match(second.out, /state:\s+recovered/);
    const newWrites = s.fake.writes.slice(writesBefore);
    assert.deepEqual(newWrites.map((w) => w.path.split('/').pop()), ['releases'], 'only the release is created, no new tag');
    assert.match(releaseOf(s.fake, '4.0.0').body, new RegExp(sha));
    assert.deepEqual(s.fake.tagNames(), ['3.1.5', '3.2.0', '4.0.0']);
  });

  test('missed events: dispatch dry run reports everything, then a live dispatch releases in order', async () => {
    s = createScenario();
    s.mergeAnchor();
    s.merge(80, { title: 'fix: a' });
    s.merge(81, { title: 'feat: b' }, { ci: 'pending' });
    const dry = await dispatch(s, {});
    assert.equal(dry.code, 0);
    assert.match(dry.out, /DRY RUN/);
    assert.match(dry.out, /PR #44[\s\S]*next tag:\s+4\.0\.0[\s\S]*PR #80[\s\S]*next tag:\s+4\.0\.1[\s\S]*PR #81[\s\S]*state:\s+pending/);
    assert.deepEqual(s.fake.writes, [], 'dry run makes zero write calls');
    const live = await dispatch(s, { dry_run: false });
    assert.equal(live.code, 0, live.out);
    assert.deepEqual(s.fake.tagNames(), ['3.1.5', '3.2.0', '4.0.0', '4.0.1']);
  });

  test('dispatch with pr stops after that PR', async () => {
    s = createScenario();
    s.mergeAnchor();
    s.merge(82, { title: 'fix: a' });
    await dispatch(s, { pr: '44', dry_run: false });
    assert.deepEqual(s.fake.tagNames(), ['3.1.5', '3.2.0', '4.0.0']);
  });

  test('dry run over a tag-only state and a ready PR performs zero writes', async () => {
    s = createScenario();
    s.mergeAnchor();
    s.fake.failures.createRelease = 1;
    await s.cli(['--execute']);
    s.merge(83, { title: 'fix: a' });
    const writes = s.fake.writes.length;
    const r = await s.cli(['--dry-run']);
    assert.equal(r.code, 0, r.out);
    assert.match(r.out, /tag-only[\s\S]*PR #83[\s\S]*ready/);
    assert.equal(s.fake.writes.length, writes);
  });
});
