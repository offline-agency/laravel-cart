import { test } from 'node:test';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';

test('read-path harness emits raw measurements and validates its fixed smoke fixture', () => {
    const result = spawnSync('php', ['benchmarks/cart-read.php', '--smoke'], { encoding: 'utf8' });
    assert.equal(result.status, 0, result.stdout + result.stderr);
    const data = JSON.parse(result.stdout);
    assert.equal(data.rows, 3);
    assert.equal(data.coupons, 1);
    assert.equal(data.driver, 'SessionStorage / Laravel array session');
    assert.equal(data.fixture_payable, '72.47');
    for (const name of ['content', 'searchById', 'total', 'payableTaxBreakdown']) {
        assert.equal(data.operations[name].length, 1);
        assert.equal(typeof data.operations[name][0].cold_ns, 'number');
        assert.equal(typeof data.operations[name][0].warm_total_ns, 'number');
        assert.ok(data.operations[name][0].storage_reads > 0);
    }
});

test('read-path harness rejects unbounded or malformed dataset sizes', () => {
    const result = spawnSync('php', ['benchmarks/cart-read.php', '--rows=0'], { encoding: 'utf8' });
    assert.notEqual(result.status, 0);
    assert.match(result.stderr, /Invalid --rows/);
});
