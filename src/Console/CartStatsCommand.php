<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Console;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use OfflineAgency\LaravelCart\CartItem;
use OfflineAgency\LaravelCart\Console\Concerns\InteractsWithCartDatabase;
use OfflineAgency\LaravelCart\Support\Coerce;

use function Termwind\render;

class CartStatsCommand extends Command
{
    protected $signature = 'cart:stats
        {--instance= : Filter stats to a specific cart instance}
        {--json : Output as JSON instead of formatted display}';

    protected $description = 'Display statistics about stored carts';

    use InteractsWithCartDatabase;

    public function handle(): int
    {
        $instance = $this->option('instance');
        $start = microtime(true);

        $query = $this->cartQuery();

        if ($instance !== null) {
            $query->where('instance', $instance);
        }

        $totalCarts = $query->count();

        if ($totalCarts === 0) {
            if ($this->option('json')) {
                $this->output->writeln(json_encode([
                    'totalCarts' => 0,
                    'uniqueInstances' => 0,
                    'withCoupons' => 0,
                    'oldest' => null,
                    'newest' => null,
                    'avgItems' => 0,
                    'avgValue' => 0,
                ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

                return self::SUCCESS;
            }

            $this->renderEmpty();

            return self::SUCCESS;
        }

        $uniqueInstances = (clone $query)->distinct('instance')->count('instance');
        $withCoupons = (clone $query)->whereNotNull('coupons')->count();
        $oldest = Coerce::nullableString((clone $query)->min('created_at'));
        $newest = Coerce::nullableString((clone $query)->max('created_at'));

        $totalItems = 0;
        $totalValue = 0.0;

        (clone $query)->orderBy('identifier')->chunk(100, function (Collection $carts) use (&$totalItems, &$totalValue): void {
            foreach ($carts as $cart) {
                $content = $this->decodeContent($cart->content) ?? new Collection;
                $totalItems += $content->count();
                $totalValue += $content->sum(fn (CartItem $item): float => $item->totalPrice * $item->qty);
            }
        });

        $avgItems = round($totalItems / $totalCarts, 1);
        $avgValue = round($totalValue / $totalCarts, 2);
        $elapsed = round(microtime(true) - $start, 2);

        if ($this->option('json')) {
            $this->output->writeln(json_encode(compact(
                'totalCarts', 'uniqueInstances', 'withCoupons',
                'oldest', 'newest', 'avgItems', 'avgValue'
            ), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->renderStats(
            $totalCarts, $uniqueInstances, $withCoupons,
            $oldest, $newest, $avgItems, $avgValue, $elapsed,
        );

        return self::SUCCESS;
    }

    private function renderStats(
        int $totalCarts,
        int $uniqueInstances,
        int $withCoupons,
        ?string $oldest,
        ?string $newest,
        float $avgItems,
        float $avgValue,
        float $elapsed,
    ): void {
        render(<<<'HTML'
            <div class="mx-2 my-1">
                <div class="px-1 bg-blue-500 text-white font-bold"> Cart Statistics </div>
            </div>
        HTML);

        $rows = [
            ['Total stored carts', number_format($totalCarts)],
            ['Unique instances', (string) $uniqueInstances],
            ['Carts with coupons', number_format($withCoupons)],
            ['Oldest cart', $oldest ? Carbon::parse($oldest)->format('Y-m-d') : 'n/a'],
            ['Newest cart', $newest ? Carbon::parse($newest)->format('Y-m-d') : 'n/a'],
            ['Avg items per cart', number_format($avgItems, 1)],
            ['Avg cart value', number_format($avgValue, 2)],
        ];

        foreach ($rows as [$label, $value]) {
            $dots = str_repeat('.', max(1, 40 - mb_strlen($label)));

            render(<<<HTML
                <div class="mx-4">
                    <span class="text-gray">{$label}</span>
                    <span class="text-gray"> {$dots} </span>
                    <span class="font-bold text-white">{$value}</span>
                </div>
            HTML);
        }

        render(<<<HTML
            <div class="mx-2 mt-1">
                <span class="text-green-500">✓</span>
                <span class="text-gray ml-1">Stats computed in {$elapsed}s</span>
            </div>
        HTML);
    }

    private function renderEmpty(): void
    {
        $message = e(__('laravel-cart::cart.command_stats_empty'));

        render(<<<HTML
            <div class="mx-2 my-1">
                <span class="px-1 bg-yellow-500 text-black font-bold"> EMPTY </span>
                <span class="ml-1 text-yellow-400">{$message}</span>
            </div>
        HTML);
    }
}
