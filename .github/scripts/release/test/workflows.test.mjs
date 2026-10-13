// Static hygiene checks on the release workflows (no YAML dependency: line based).
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../../../workflows');
const read = (f) => readFileSync(resolve(root, f), 'utf8');

/** Collect the text of every `run:` step (inline or block scalar). */
function runBlocks(yaml) {
  const lines = yaml.split('\n');
  const blocks = [];
  for (let i = 0; i < lines.length; i++) {
    const m = /^(\s*)(?:- )?run:\s*(.*)$/.exec(lines[i]);
    if (!m) continue;
    const indent = m[1].length;
    const body = [m[2]];
    while (i + 1 < lines.length && (lines[i + 1].trim() === '' || lines[i + 1].search(/\S/) > indent + 2)) body.push(lines[++i]);
    blocks.push(body.join('\n'));
  }
  return blocks;
}

for (const file of ['release.yml', 'release-metadata.yml']) {
  test(`${file}: no expression interpolation inside run scripts`, () => {
    const blocks = runBlocks(read(file));
    assert.ok(blocks.length > 0);
    for (const b of blocks) assert.doesNotMatch(b, /\$\{\{/, b);
  });
  test(`${file}: default-deny permissions and pinned actions`, () => {
    const y = read(file);
    assert.match(y, /^permissions: \{\}$/m);
    for (const m of y.matchAll(/uses:\s*(\S+)/g)) assert.match(m[1], /@[0-9a-f]{40}$/, m[1]);
  });
}

test('release.yml: single serialized publisher, trusted checkout, no PR head refs', () => {
  const y = read('release.yml');
  assert.match(y, /group: release\n\s*cancel-in-progress: false/);
  assert.match(y, /ref: \$\{\{ github\.sha \}\}/);
  assert.match(y, /persist-credentials: false/);
  assert.doesNotMatch(y, /pull_request\.head|head_ref|github\.event\.pull_request\.(title|body)/);
  assert.equal((y.match(/contents: write/g) ?? []).length, 1);
  assert.doesNotMatch(y, /composer/);
});

test('release-metadata.yml: read-only', () => {
  const y = read('release-metadata.yml');
  assert.doesNotMatch(y, /: write/);
  assert.doesNotMatch(y, /pull_request_target/);
});
