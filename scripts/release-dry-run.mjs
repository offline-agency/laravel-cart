// Read-only rehearsal: the existing test transport writes exclusively to temp git/fake API.
import { createScenario } from '../.github/scripts/release/test/helpers/scenario.mjs';
import { spawnSync } from 'node:child_process';
import assert from 'node:assert/strict';

const tags = spawnSync('git', ['tag', '--list'], { encoding: 'utf8' });
if (tags.status !== 0) throw new Error(tags.stderr);
const numeric = tags.stdout.trim().split('\n').filter(v => /^\d+\.\d+\.\d+$/.test(v));
numeric.sort((a, b) => {
    const aa = a.split('.').map(Number), bb = b.split('.').map(Number);
    return aa[0] - bb[0] || aa[1] - bb[1] || aa[2] - bb[2];
});
const previous = numeric.at(-1);
if (!previous) throw new Error('No stable numeric previous version');
const candidate = { title: 'feat: make cart mutations atomic and add opt-in guest transfer', body: 'Coherent repricing, transactional mutations and transfer, versioned docs and optional Boost guidance.', labels: ['release:minor'] };
const scenario = createScenario();
try {
    const anchor = scenario.mergeAnchor();
    assert.equal((await scenario.cli(['--execute'])).code, 0); // fake transport only
    if (!scenario.fake.tagNames().includes(previous)) scenario.fake.addTag(previous, anchor);
    const target = scenario.merge(900, candidate, { ci: 'success' });
    const writes = scenario.fake.writes.length;
    const result = await scenario.cli(['--dry-run']);
    assert.equal(result.code, 0, result.out);
    assert.equal(scenario.fake.writes.length, writes);
    console.log(JSON.stringify({ fixture: true, previous, candidate, target, check: 'CI passed: success (fake exact merge SHA)', dryRunExit: result.code, remoteWrites: 0 }, null, 2));
    console.log(result.out);
    console.log('No real merged candidate exists. A future merge must verify the accepted API merge SHA and all required post-merge jobs.');
} finally { scenario.cleanup(); }
