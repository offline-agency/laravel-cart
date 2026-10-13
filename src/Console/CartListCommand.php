<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Console;

use Carbon\Carbon;
use Illuminate\Console\Command;
use OfflineAgency\LaravelCart\Console\Concerns\InteractsWithCartDatabase;
use OfflineAgency\LaravelCart\Support\Coerce;
use stdClass;

use function Termwind\render;

class CartListCommand extends Command
{
    protected $signature = 'cart:list
        {--instance= : Filter by cart instance name}
        {--limit=50 : Maximum number of carts to display}
        {--sort=newest : Sort order: newest, oldest, or identifier}
        {--json : Output as JSON array}';

    protected $description = 'List all stored carts';

    use InteractsWithCartDatabase;

    public function handle(): int
    {
        $instance = $this->option('instance');
        $limit = (int) $this->option('limit');
        $sort = $this->option('sort');

        $query = $this->cartQuery();

        if ($instance !== null) {
            $query->where('instance', $instance);
        }

        $total = (clone $query)->count();

        if ($total === 0) {
            if ($this->option('json')) {
                $this->output->writeln(json_encode([], JSON_THROW_ON_ERROR));

                return self::SUCCESS;
            }

            $this->renderEmpty();

            return self::SUCCESS;
        }

        match ($sort) {
            'oldest' => $query->orderBy('created_at', 'asc'),
            'identifier' => $query->orderBy('identifier', 'asc'),
            default => $query->orderBy('created_at', 'desc'),
        };

        $carts = $query->limit($limit)->get();

        $rows = $carts->map(function (stdClass $cart): array {
            $content = $this->decodeContent($cart->content);
            $coupons = $this->decodeCoupons($cart->coupons ?? null);
            $createdAt = Coerce::nullableString($cart->created_at);

            $couponCount = count($coupons);

            return [
                'identifier' => Coerce::string($cart->identifier),
                'instance' => Coerce::string($cart->instance),
                'items' => $content?->count(),
                'coupons' => $couponCount,
                'created_at' => $createdAt,
                'updated_at' => $cart->updated_at,
            ];
        });

        if ($this->option('json')) {
            $this->output->writeln(json_encode($rows->values()->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->renderHeader();

        foreach ($rows as $row) {
            $this->renderCartRow($row);
        }

        $this->renderFooter($rows->count(), $total);

        return self::SUCCESS;
    }

    private function renderHeader(): void
    {
        render(<<<'HTML'
            <div class="mx-2 my-1">
                <div class="px-1 bg-blue-500 text-white font-bold"> Stored Carts </div>
            </div>
        HTML);
    }

    /**
     * @param  array{identifier: string, instance: string, items: int|null, coupons: int, created_at: string|null, updated_at: mixed}  $row
     */
    private function renderCartRow(array $row): void
    {
        $identifier = e(str_pad(mb_substr($row['identifier'], 0, 14), 14));
        $instance = e(str_pad($row['instance'], 12));
        $itemCount = $row['items'];
        $items = $itemCount !== null
            ? str_pad($itemCount.' item'.($itemCount !== 1 ? 's' : ''), 10)
            : str_pad('? items', 10);
        $couponCount = $row['coupons'];
        $coupons = $couponCount > 0
            ? str_pad($couponCount.' coupon'.($couponCount !== 1 ? 's' : ''), 12)
            : str_pad('─', 12);
        $date = $row['created_at'] ? Carbon::parse($row['created_at'])->format('Y-m-d H:i') : 'n/a';

        render(<<<HTML
            <div class="mx-4">
                <span class="text-blue-400 w-16">{$identifier}</span>
                <span class="text-gray w-14">{$instance}</span>
                <span class="text-white w-12">{$items}</span>
                <span class="text-yellow-400 w-14">{$coupons}</span>
                <span class="text-gray">{$date}</span>
            </div>
        HTML);
    }

    private function renderFooter(int $shown, int $total): void
    {
        $footer = e(__('laravel-cart::cart.command_list_footer', ['shown' => $shown, 'total' => $total]));

        render(<<<HTML
            <div class="mx-2 mt-1">
                <span class="text-gray">{$footer}</span>
            </div>
        HTML);
    }

    private function renderEmpty(): void
    {
        $empty = e(__('laravel-cart::cart.command_list_empty'));

        render(<<<HTML
            <div class="mx-2 my-1">
                <span class="px-1 bg-yellow-500 text-black font-bold"> EMPTY </span>
                <span class="ml-1 text-yellow-400">{$empty}</span>
            </div>
        HTML);
    }
}
