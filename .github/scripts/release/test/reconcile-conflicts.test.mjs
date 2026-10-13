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

describe('conflicts', () => {
  test('existing tag whose marker names a different SHA for the PR: blocked, no writes', async () => {
    s = createScenario();
    const sha = s.mergeAnchor();
    const other = s.repo.commit('chore: something');
    s.fake.addTag('4.0.0', other, {
      message: `<!-- laravel-cart-release {"pr":44,"sha":"${other}","bump":"major","reason":"x","version":"4.0.0","previous":"3.2.0"} -->`,
    });
    const r = await s.cli(['--execute']);
    assert.equal(r.code, 1);
    assert.match(r.out, /marker names PR #44 @ [0-9a-f]{40}, but the PR's merge commit is/);
    assert.ok(sha);
    assert.deepEqual(s.fake.writes, []);
  });

  test('computed version already taken by another commit: blocked, no writes', async () => {
    s = createScenario();
    s.repo.branch('hotfix', s.sha43);
    const hot = s.repo.commit('hotfix', 'hotfix');
    s.fake.addTag('4.0.0', hot);
    s.mergeAnchor();
    const r = await s.cli(['--execute']);
    assert.equal(r.code, 1);
    assert.match(r.out, /computed version 4\.0\.0 is already taken by commit/);
    assert.deepEqual(s.fake.writes, []);
  });

  test('unmarked stable tag already on the merge commit: blocked', async () => {
    s = createScenario();
    const sha = s.mergeAnchor();
    s.fake.addTag('4.0.0', sha);
    const r = await s.cli(['--execute']);
    assert.equal(r.code, 1);
    assert.match(r.out, /already points at/);
    assert.deepEqual(s.fake.writes, []);
  });

  test('a higher non-ancestor tag does not change the base version and disables make_latest', async () => {
    s = createScenario();
    s.repo.branch('side');
    s.fake.addTag('9.9.9', s.repo.commit('side', 'side'));
    s.mergeAnchor();
    await s.cli(['--execute']);
    assert.equal(releaseOf(s.fake, '4.0.0').make_latest, 'false');
  });
});
