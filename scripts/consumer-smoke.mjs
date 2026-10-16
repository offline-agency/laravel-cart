import { mkdtemp, mkdir, readFile, writeFile, access } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { spawnSync } from 'node:child_process';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const work = await mkdtemp(join(tmpdir(), 'laravel-cart-consumer-'));
console.log(`Disposable consumer: ${work}`);
function run(command, args, cwd = work, expected = 0) {
    const result = spawnSync(command, args, { cwd, encoding: 'utf8', maxBuffer: 10 * 1024 * 1024 });
    if (result.status !== expected) throw new Error(`${command} ${args.join(' ')} exited ${result.status}\n${result.stdout}\n${result.stderr}`);
    return result.stdout + result.stderr;
}
await mkdir(join(work, 'archive'));
run('composer', ['archive', '--format=zip', '--file=cart', `--dir=${join(work, 'archive')}`], root);
const manifest = JSON.parse(await readFile(join(root, 'composer.json'), 'utf8'));
// This fixture version is local repository metadata only; no tag/release is created.
const packageData = { ...manifest, version: '4.999.0', dist: { type: 'zip', url: `file://${join(work, 'archive/cart.zip')}` } };
delete packageData['require-dev'];
const composer = {
    name: 'cart-tests/disposable-consumer',
    require: { 'offline-agency/laravel-cart': '4.999.0', 'laravel/framework': '^13.32' },
    repositories: [{ type: 'package', package: packageData }],
    config: { 'allow-plugins': false, platform: { 'ext-bcmath': false } },
};
await writeFile(join(work, 'composer.json'), JSON.stringify(composer, null, 2));
const rejected = spawnSync('composer', ['update', '--no-dev', '--no-interaction', '--no-progress'], { cwd: work, encoding: 'utf8' });
if (rejected.status === 0 || !`${rejected.stdout}${rejected.stderr}`.includes('ext-bcmath')) throw new Error('Missing BCMath was not rejected by Composer');
console.log('Missing BCMath: Composer platform rejection verified before runtime.');
delete composer.config.platform;
await writeFile(join(work, 'composer.json'), JSON.stringify(composer, null, 2));
run('composer', ['update', '--no-dev', '--no-interaction', '--no-progress']);
const installed = JSON.parse(await readFile(join(work, 'vendor/composer/installed.json'), 'utf8')).packages;
if (installed.some(p => /^(pestphp\/|phpunit\/|orchestra\/testbench|larastan\/)/.test(p.name))) throw new Error('Package development dependencies leaked into consumer');
for (const path of ['resources/boost/guidelines/core.blade.php', 'resources/boost/skills/laravel-cart-development/SKILL.md', 'docs/v4/index.md', 'examples/quick-start.php']) {
    await access(join(work, 'vendor/offline-agency/laravel-cart', path));
}
for (const path of ['bootstrap/cache', 'storage/framework/views', 'storage/framework/sessions', 'storage/logs', 'config']) await mkdir(join(work, path), { recursive: true });
await writeFile(join(work, 'config/app.php'), `<?php return ['env' => 'local', 'debug' => true];\n`);
await writeFile(join(work, 'bootstrap/app.php'), `<?php
return Illuminate\\Foundation\\Application::configure(basePath: dirname(__DIR__))->create();
`);
await writeFile(join(work, 'artisan'), `<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
exit($app->handleCommand(new Symfony\\Component\\Console\\Input\\ArgvInput));
`);
await writeFile(join(work, 'smoke.php'), `<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
config(['session.driver' => 'array']);
$cart = app('cart');
require __DIR__.'/vendor/offline-agency/laravel-cart/examples/quick-start.php';
if (OfflineAgency\\LaravelCart\\Examples\\QuickStart::run($cart)['payable'] !== 45.36) { throw new RuntimeException('Installed example failed'); }
$cart->destroy();
config(['cart.format.decimals' => 3]);
$cart->add(id: 1, name: 'Precision', qty: 1, price: 1.235, vat: 0.0);
$cart->addCoupon(new OfflineAgency\\LaravelCart\\CartCoupon(hash: 'p', code: 'P', type: 'percentage', value: 10.0));
if ($cart->globalCouponDiscount('1.235') !== '0.124' || $cart->total() !== 1.111) { throw new RuntimeException('Consumer precision failed'); }
echo "Consumer fixed/percentage coupons and example: passed\\n";
`);
console.log(run('php', ['artisan', 'package:discover', '--no-interaction']));
console.log(run('php', ['smoke.php']));
// Boost remains optional and is installed only in this disposable app's dev section.
run('composer', ['require', '--dev', 'laravel/boost:^2.10.3', '--no-interaction', '--no-progress']);
console.log(run('php', ['artisan', 'package:discover', '--no-interaction']));
console.log(run('php', ['artisan', 'boost:install', '--help']));
console.log(`Archive/install verified. Boost app ready at ${work}; rendering/discovery check follows.`);
await writeFile(join(work, 'boost-smoke.php'), await readFile(join(root, 'scripts/boost-smoke.php')));
console.log(run('php', ['boost-smoke.php']));
