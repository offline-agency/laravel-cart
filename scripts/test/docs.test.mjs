import { test } from 'node:test';
import assert from 'node:assert/strict';
import { mkdtemp, cp, mkdir, readFile, writeFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { spawnSync } from 'node:child_process';

async function fixture() {
    const dir = await mkdtemp(join(tmpdir(), 'cart-docs-test-'));
    for (const path of ['docs', 'examples', 'README.md', 'UPGRADE.md', 'CONTRIBUTING.md', 'LARASTAN.md', 'SECURITY.md']) {
        await cp(resolve(path), join(dir, path), { recursive: true });
    }
    for (const path of ['scripts', 'src']) await mkdir(join(dir, path));
    await cp(resolve('scripts/docs.mjs'), join(dir, 'scripts/docs.mjs'));
    await cp(resolve('src/CartServiceProvider.php'), join(dir, 'src/CartServiceProvider.php'));
    // Link targets referenced by docs; their content is not executed.
    for (const path of ['config', 'tests', 'resources', '.github', 'CHANGELOG.md', 'LICENSE', 'composer.json']) {
        await cp(resolve(path), join(dir, path), { recursive: true });
    }
    return dir;
}
for (const [name, addition] of [
    ['invalid link', '\n[missing](missing.md)\n'],
    ['invalid anchor', '\n[missing](usage.md#absent-anchor)\n'],
    ['wrong publish tag', '\n`--tag=nonexistent-cart-tag`\n'],
    ['stale generated example', null],
]) {
    test(`documentation checker rejects ${name}`, async () => {
        const dir = await fixture();
        try {
            assert.equal(spawnSync('node', ['scripts/docs.mjs'], { cwd: dir }).status, 0);
            const path = join(dir, 'docs/v4/usage.md');
            let content = await readFile(path, 'utf8');
            content = addition ? content + addition : content.replace('$payable = $cart->total()', '$payable = $cart->brokenMethod()');
            await writeFile(path, content);
            assert.notEqual(spawnSync('node', ['scripts/docs.mjs'], { cwd: dir }).status, 0);
        } finally { await rm(dir, { recursive: true, force: true }); }
    });
}

for (const [name, path, before, after] of [
    ['recipe snippet', 'docs/v4/recipes/index.md', '$this->cart->add(', '$this->cart->missing('],
    ['tax snippet', 'docs/v4/post-discount-tax.md', '$cart->payableTaxBreakdown()', '$cart->missing()'],
]) {
    test(`documentation checker rejects stale ${name}`, async () => {
        const dir = await fixture();
        try {
            assert.equal(spawnSync('node', ['scripts/docs.mjs'], { cwd: dir }).status, 0);
            const file = join(dir, path);
            await writeFile(file, (await readFile(file, 'utf8')).replace(before, after));
            assert.notEqual(spawnSync('node', ['scripts/docs.mjs'], { cwd: dir }).status, 0);
        } finally { await rm(dir, { recursive: true, force: true }); }
    });
}
