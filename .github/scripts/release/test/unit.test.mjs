import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { bumpVersion, compareVersions, parseVersion } from '../lib/semver.mjs';
import { classify } from '../lib/classify.mjs';
import { formatMarker, parseMarker } from '../lib/marker.mjs';
import { buildReleaseBody, extractUpgradeSections } from '../lib/notes.mjs';
import { interpretEvent } from '../lib/events.mjs';
import { validatePullRequest } from '../validate-pr.mjs';
import { loadConfig } from '../cli.mjs';

const config = { ...loadConfig(), repository: 'acme/cart' };
const SHA = 'a'.repeat(40);

describe('semver', () => {
  test('parses only stable X.Y.Z', () => {
    assert.deepEqual(parseVersion('3.2.0'), [3, 2, 0]);
    assert.equal(parseVersion('v3.2.0'), null);
    assert.equal(parseVersion('4.0.0-rc.1'), null);
  });
  test('numeric comparison (3.10.0 > 3.9.9)', () => {
    assert.equal(compareVersions('3.10.0', '3.9.9'), 1);
    assert.equal(compareVersions('3.2.0', '3.2.0'), 0);
  });
  test('bumps', () => {
    assert.equal(bumpVersion('3.2.0', 'major'), '4.0.0');
    assert.equal(bumpVersion('3.2.7', 'minor'), '3.3.0');
    assert.equal(bumpVersion('3.2.7', 'patch'), '3.2.8');
  });
});

describe('classify', () => {
  const c = (title, { body = '', labels = [], override } = {}) => classify({ title, body, labels, override });
  test('major from bang title', () => assert.equal(c('feat(cart)!: new storage').bump, 'major'));
  test('major from BREAKING CHANGE footer', () => assert.equal(c('feat: x', { body: 'text\n\nBREAKING CHANGE: dropped L10' }).bump, 'major'));
  test('major from label', () => {
    const r = c('Release v4', { labels: [{ name: 'release:major' }] });
    assert.deepEqual([r.ok, r.bump, r.source], [true, 'major', 'label']);
  });
  test('minor from feat', () => assert.equal(c('feat(coupons): add coupons').bump, 'minor'));
  test('patch from docs and other patch types', () => {
    for (const t of ['docs: readme', 'fix: bug', 'chore(deps): bump', 'deps: bump x', 'ci: tweak', 'revert: x']) {
      assert.equal(c(t).bump, 'patch', t);
    }
  });
  test('fallback patch for unclassified title', () => {
    const r = c('Update README.md');
    assert.deepEqual([r.ok, r.bump, r.source], [true, 'patch', 'fallback']);
  });
  test('label and title agreeing is fine', () => assert.equal(c('feat: x', { labels: ['release:minor'] }).bump, 'minor'));
  test('label vs title conflict', () => {
    const r = c('fix: small', { labels: ['release:major'] });
    assert.equal(r.ok, false);
    assert.match(r.conflict, /disagrees/);
  });
  test('multiple release labels conflict', () => {
    assert.equal(c('fix: x', { labels: ['release:patch', 'release:minor'] }).ok, false);
  });
  test('config override acts like a label', () => {
    assert.equal(c('v4.0.0: big', { override: { bump: 'major', reason: 'x' } }).bump, 'major');
    assert.equal(c('fix: x', { override: 'major' }).ok, false);
    assert.equal(c('x', { override: 'major', labels: ['release:minor'] }).ok, false);
  });
});

describe('marker', () => {
  test('round trip, with HTML-unsafe characters escaped', () => {
    const text = `Release\n\n${formatMarker('laravel-cart-release', { pr: 44, sha: SHA, bump: 'major', reason: 'a --> b <c>', version: '4.0.0', previous: '3.2.0' })}\n`;
    assert.ok(!/\{[^}]*-->[^}]*\}/.test(text));
    const parsed = parseMarker('laravel-cart-release', text);
    assert.equal(parsed.ok, true);
    assert.deepEqual(parsed.marker, { pr: 44, sha: SHA, bump: 'major', reason: 'a --> b <c>', version: '4.0.0', previous: '3.2.0' });
  });
  test('absent and malformed markers', () => {
    assert.equal(parseMarker('laravel-cart-release', 'Release 3.2.0'), null);
    assert.equal(parseMarker('laravel-cart-release', '<!-- laravel-cart-release {"pr":"x"} -->').ok, false);
  });
});

describe('release notes', () => {
  test('extracts breaking/migration/upgrade sections, ignoring fenced headings', () => {
    const body = '## Summary\nx\n## Breaking changes\n- a\n```\n# Upgrade not a heading\n```\n### detail\ny\n## Upgrade guide\nrun z\n## Test plan\n- t';
    const s = extractUpgradeSections(body);
    assert.deepEqual(s.map((x) => x.heading), ['Breaking changes', 'Upgrade guide']);
    assert.match(s[0].text, /### detail/);
    assert.doesNotMatch(s[1].text, /Test plan/);
  });
  test('body contains PR link, sha, bump, compare link and marker', () => {
    const body = buildReleaseBody({
      repository: 'acme/cart', version: '4.0.0', previous: '3.2.0', sha: SHA, bump: 'major', reason: 'label', marker: '<!-- m -->',
      pr: { number: 44, title: 'Big', html_url: 'https://github.com/acme/cart/pull/44', body: '## Migration\nDo X' },
    });
    for (const part of ['[#44 Big](https://github.com/acme/cart/pull/44)', SHA, '**major**', 'compare/3.2.0...4.0.0', '### Migration', 'Do X', '<!-- m -->']) {
      assert.ok(body.includes(part), part);
    }
  });
});

describe('event interpretation', () => {
  const base = { repository: 'acme/cart', ref: 'refs/heads/main', config };
  const wr = (o = {}) => ({
    action: 'completed',
    workflow_run: {
      id: 1, path: '.github/workflows/ci.yml', event: 'push', head_branch: 'main', head_sha: SHA,
      head_repository: { full_name: 'acme/cart' }, repository: { full_name: 'acme/cart' }, ...o,
    },
  });
  const ev = (eventName, payload, extra = {}) => interpretEvent({ ...base, eventName, payload, ...extra });

  test('accepts CI push run on main', () => assert.equal(ev('workflow_run', wr()).action, 'reconcile'));
  test('accepts path with @ref suffix', () => assert.equal(ev('workflow_run', wr({ path: '.github/workflows/ci.yml@refs/heads/main' })).action, 'reconcile'));
  test('rejects workflow_run from a fork', () => {
    assert.equal(ev('workflow_run', wr({ head_repository: { full_name: 'evil/cart' } })).action, 'ignore');
  });
  test('rejects workflow_run from another branch', () => assert.equal(ev('workflow_run', wr({ head_branch: 'develop' })).action, 'ignore'));
  test('rejects workflow_run of another workflow', () => assert.equal(ev('workflow_run', wr({ path: '.github/workflows/pint.yml' })).action, 'ignore'));
  test('rejects workflow_run of pull_request CI', () => assert.equal(ev('workflow_run', wr({ event: 'pull_request' })).action, 'ignore'));
  test('rejects events in another repository (fork of the repo)', () => {
    assert.equal(ev('workflow_run', wr(), { repository: 'someone/cart' }).action, 'ignore');
  });
  const prPayload = (o = {}) => ({ action: 'closed', pull_request: { number: 5, merged: true, base: { ref: 'main', repo: { full_name: 'acme/cart' } }, ...o } });
  test('pull_request_target merged into main', () => assert.equal(ev('pull_request_target', prPayload()).action, 'reconcile'));
  test('pull_request_target closed-unmerged ignored', () => assert.equal(ev('pull_request_target', prPayload({ merged: false })).action, 'ignore'));
  test('pull_request_target other base ignored', () => {
    assert.equal(ev('pull_request_target', prPayload({ base: { ref: 'develop', repo: { full_name: 'acme/cart' } } })).action, 'ignore');
  });
  test('pull_request_target from a fork head is accepted (only the merge commit matters)', () => {
    assert.equal(ev('pull_request_target', prPayload({ head: { repo: { full_name: 'fork/cart' } } })).action, 'reconcile');
  });
  test('dispatch defaults to dry run; parses pr; rejects bad input and non-main ref', () => {
    assert.deepEqual(ev('workflow_dispatch', { inputs: {} }), { action: 'reconcile', dryRun: true, targetPr: null, trigger: 'manual dispatch' });
    const r = ev('workflow_dispatch', { inputs: { pr: '44', dry_run: false } });
    assert.deepEqual([r.dryRun, r.targetPr], [false, 44]);
    assert.equal(ev('workflow_dispatch', { inputs: { pr: '1; rm -rf' } }).action, 'error');
    assert.equal(ev('workflow_dispatch', { inputs: {} }, { ref: 'refs/heads/develop' }).action, 'ignore');
  });
});

describe('pre-merge validation', () => {
  test('passes and fails as expected', () => {
    assert.equal(validatePullRequest({ number: 9, title: 'docs: x', labels: [] }, config).ok, true);
    assert.equal(validatePullRequest({ number: 9, title: 'docs: x', labels: [{ name: 'release:minor' }] }, config).ok, false);
    assert.equal(validatePullRequest({ number: 9, title: 'x', labels: [{ name: 'release:minor' }, { name: 'release:major' }] }, config).ok, false);
    assert.match(validatePullRequest({ number: 44, title: 'v4.0.0: big', labels: [] }, config).message, /major/);
  });
});
