<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use OfflineAgency\LaravelCart\Cart;
use OfflineAgency\LaravelCart\CartCoupon;
use OfflineAgency\LaravelCart\CartItem;
use OfflineAgency\LaravelCart\CartServiceProvider;
use OfflineAgency\LaravelCart\Storage\SessionStorage;

require __DIR__.'/../vendor/autoload.php';

function cartBenchInt(mixed $value, string $name, int $min, int $max): int
{
    if (! is_string($value) || ! ctype_digit($value) || (int) $value < $min || (int) $value > $max) {
        throw new InvalidArgumentException("Invalid --{$name}; expected {$min}..{$max}.");
    }

    return (int) $value;
}

try {
    $options = getopt('', ['rows:', 'coupons:', 'iterations:', 'samples:', 'smoke']);
    if ($options === false) {
        throw new InvalidArgumentException('Invalid benchmark options.');
    }
    $smoke = isset($options['smoke']);
    $rows = cartBenchInt($options['rows'] ?? ($smoke ? '3' : '100'), 'rows', 1, 2000);
    $coupons = cartBenchInt($options['coupons'] ?? ($smoke ? '1' : '5'), 'coupons', 0, 20);
    $iterations = cartBenchInt($options['iterations'] ?? ($smoke ? '2' : '10'), 'iterations', 1, 1000);
    $samples = cartBenchInt($options['samples'] ?? ($smoke ? '1' : '3'), 'samples', 1, 10);
} catch (InvalidArgumentException $exception) {
    fwrite(STDERR, $exception->getMessage()."\n");
    exit(1);
}
$work = sys_get_temp_dir().'/cart-read-bench-'.Str::random(16);
$filesystem = new Filesystem;
$failed = false;
try {
    $setup = hrtime(true);
    foreach (['bootstrap/cache', 'config', 'storage/framework/views', 'storage/framework/sessions', 'storage/logs'] as $directory) {
        $filesystem->makeDirectory($work.'/'.$directory, 0755, true);
    }
    $app = Application::configure(basePath: $work)->withProviders([CartServiceProvider::class])->create();
    $app->make(Kernel::class)->bootstrap();
    config(['session.driver' => 'array']);
    $storage = new class($app->make(SessionManager::class)) extends SessionStorage
    {
        public int $reads = 0;

        public function get(string $key, mixed $default = null): mixed
        {
            $this->reads++;

            return parent::get($key, $default);
        }
    };
    // Seed outside measurement; this harness measures reads, not bulk mutation throughput.
    $items = [];
    for ($i = 0; $i < $rows; $i++) {
        $item = new CartItem('sku-'.$i, 'Fixture '.$i, '', ($i % 3) + 1, 10.0, 12.2, vat: 2.2, options: ['size' => $i % 2]);
        $item->associate(stdClass::class);
        $items[$item->rowId] = $item;
    }
    $storage->put('cart.default', new Collection($items));
    $cartCoupons = [];
    for ($i = 0; $i < $coupons; $i++) {
        $cartCoupons['p'.$i] = new CartCoupon(hash: 'p'.$i, code: 'P'.$i, type: 'percentage', value: 1.0);
    }
    $storage->put('cart.default_global_coupons', new Collection($cartCoupons));
    $events = $app->make(Dispatcher::class);
    $reference = new Cart($storage, $events);
    if ($smoke && ($reference->total() !== 72.47 || $reference->payableTaxBreakdown()->totals['gross'] !== '72.47'
        || $reference->searchById('sku-2', stdClass::class)?->id !== 'sku-2' || $reference->content()->count() !== 3)) {
        throw new LogicException('Fixed smoke fixture failed its independent payable/identity expectations.');
    }
    $result = ['schema' => 1, 'timestamp_utc' => gmdate(DATE_ATOM), 'php' => PHP_VERSION, 'laravel' => $app->version(),
        'os' => PHP_OS_FAMILY, 'architecture' => php_uname('m'), 'opcache_cli' => ini_get('opcache.enable_cli'),
        'driver' => 'SessionStorage / Laravel array session', 'rows' => $rows, 'coupons' => $coupons,
        'quantity_pattern' => '1,2,3 repeated; net 10 / VAT 2.20 per unit; 1% sequential cart coupons',
        'setup_ns' => hrtime(true) - $setup, 'fixture_payable' => number_format($reference->total(), 2, '.', ''), 'operations' => []];
    $operations = [
        'content' => fn (Cart $cart) => $cart->content(),
        'searchById' => fn (Cart $cart): ?CartItem => $cart->searchById('sku-'.($rows - 1), stdClass::class),
        'total' => fn (Cart $cart): float => $cart->total(),
        'payableTaxBreakdown' => fn (Cart $cart) => $cart->payableTaxBreakdown(),
    ];
    foreach ($operations as $name => $operation) {
        $result['operations'][$name] = [];
        for ($sample = 0; $sample < $samples; $sample++) {
            $cart = new Cart($storage, $events); // Fresh object, same in-process session fixture.
            $beforeReads = $storage->reads;
            $start = hrtime(true);
            $operation($cart);
            $cold = hrtime(true) - $start;
            $operation($cart); // Unmeasured warm-up.
            $start = hrtime(true);
            for ($iteration = 0; $iteration < $iterations; $iteration++) {
                $operation($cart);
            }
            $warm = hrtime(true) - $start;
            $result['operations'][$name][] = ['cold_ns' => $cold, 'warm_total_ns' => $warm,
                'warm_iterations' => $iterations, 'storage_reads' => $storage->reads - $beforeReads];
        }
    }
    $result['process_peak_bytes'] = memory_get_peak_usage(true);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class.': '.$exception->getMessage()."\n");
    $failed = true;
} finally {
    $filesystem->deleteDirectory($work);
}
if ($failed) {
    exit(1);
}
