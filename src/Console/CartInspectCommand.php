<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Console;

use Illuminate\Console\Command;
use OfflineAgency\LaravelCart\Console\Concerns\InteractsWithCartDatabase;

use function Termwind\{render};

class CartInspectCommand extends Command
{
    protected $signature = 'cart:inspect
        {identifier : The cart identifier (e.g., user ID)}
        {--instance=default : The cart instance to inspect}
        {--json : Output as JSON}
        {--raw : Show raw serialized content}';

    protected $description = 'Inspect a specific stored cart in detail';

    use InteractsWithCartDatabase;

    public function handle(): int
    {
        $identifier = $this->argument('identifier');
        $instance = $this->option('instance');

        $stored = $this->cartQuery()
            ->where('identifier', $identifier)
            ->where('instance', $instance)
            ->first();

        if ($stored === null) {
            if ($this->option('json')) {
                $this->output->writeln(json_encode(['error' => 'Not found'], JSON_THROW_ON_ERROR));

                return self::FAILURE;
            }

            $this->renderNotFound($identifier, $instance);

            return self::FAILURE;
        }

        if ($this->option('raw')) {
            render(<<<'HTML'
                <div class="mx-2 my-1">
                    <div class="px-1 bg-gray-700 text-gray-300 font-bold"> Raw Content </div>
                </div>
            HTML);
            $this->output->writeln($stored->content);

            return self::SUCCESS;
        }

        $content = $this->deserializeContent($stored->content);

        if ($content->isEmpty() && $stored->content !== '' && $stored->content !== serialize(collect())) {
            if ($this->option('json')) {
                $this->output->writeln(json_encode([
                    'error' => 'Failed to deserialize cart content',
                    'identifier' => $identifier,
                    'instance' => $instance,
                ], JSON_THROW_ON_ERROR));

                return self::FAILURE;
            }

            $this->renderDeserializeError();

            return self::FAILURE;
        }

        $coupons = $this->decodeCoupons($stored->coupons ?? null);

        if ($this->option('json')) {
            $this->output->writeln(json_encode([
                'identifier' => $stored->identifier,
                'instance' => $stored->instance,
                'items' => $content->map(fn ($item) => $item->toArray())->values()->toArray(),
                'coupons' => $coupons,
                'created_at' => $stored->created_at,
                'updated_at' => $stored->updated_at,
            ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $date = $stored->created_at ? date('Y-m-d H:i:s', strtotime($stored->created_at)) : 'n/a';
        $this->renderHeader(e($identifier), e($instance), $date);

        if ($content->isNotEmpty()) {
            $this->renderItemsSection($content);
        }

        if (count($coupons) > 0) {
            $this->renderCouponsSection($coupons);
        }

        if ($content->isNotEmpty()) {
            $this->renderTotals($content);
        }

        return self::SUCCESS;
    }

    private function renderHeader(string $identifier, string $instance, string $date): void
    {
        render(<<<HTML
            <div class="mx-2 my-1">
                <div class="px-1 bg-blue-500 text-white font-bold">
                    Cart: {$identifier}
                    <span class="text-blue-200 ml-1">· instance: {$instance} · {$date}</span>
                </div>
            </div>
        HTML);
    }

    private function renderItemsSection($content): void
    {
        $count = $content->count();

        render(<<<HTML
            <div class="mx-2 mt-1">
                <span class="text-gray font-bold"> Items ({$count})</span>
            </div>
        HTML);

        foreach ($content as $item) {
            $this->renderItemRow($item);
        }
    }

    private function renderItemRow(object $item): void
    {
        $rowId = e(mb_substr($item->rowId, 0, 8).'...');
        $id = e(str_pad(mb_substr((string) $item->id, 0, 8), 8));
        $name = e(str_pad(mb_substr($item->name, 0, 22), 22));
        $qty = str_pad('×'.$item->qty, 5);
        $price = str_pad(number_format((float) $item->price, 2), 10, ' ', STR_PAD_LEFT);
        $lineTotal = str_pad(
            number_format((float) $item->totalPrice * $item->qty, 2), 12, ' ', STR_PAD_LEFT
        );

        render(<<<HTML
            <div class="mx-4">
                <span class="text-gray">{$rowId}</span>
                <span class="text-blue-300 ml-1">{$id}</span>
                <span class="text-white ml-1">{$name}</span>
                <span class="text-yellow-300 ml-1">{$qty}</span>
                <span class="text-gray ml-1">{$price}</span>
                <span class="text-green-400 ml-1 font-bold">{$lineTotal}</span>
            </div>
        HTML);
    }

    private function renderCouponsSection(array $coupons): void
    {
        render(<<<'HTML'
            <div class="mx-2 mt-1">
                <span class="text-gray font-bold"> Coupons</span>
            </div>
        HTML);

        foreach ($coupons as $coupon) {
            $this->renderCouponRow($coupon);
        }
    }

    private function renderCouponRow(array $coupon): void
    {
        $code = e(str_pad($coupon['code'] ?? '?', 14));
        $type = e(str_pad($coupon['type'] ?? '?', 12));
        $val = ($coupon['type'] ?? '') === 'percentage'
            ? (($coupon['value'] ?? 0).'%')
            : number_format((float) ($coupon['value'] ?? 0), 2);
        $value = str_pad($val, 8);
        $scope = ($coupon['isGlobal'] ?? false) ? 'global' : 'item';

        render(<<<HTML
            <div class="mx-4">
                <span class="text-cyan-400">{$code}</span>
                <span class="text-gray ml-1">{$type}</span>
                <span class="text-white ml-1">{$value}</span>
                <span class="text-gray ml-1">{$scope}</span>
            </div>
        HTML);
    }

    private function renderTotals($content): void
    {
        $subtotal = $content->sum(fn ($item) => $item->price * $item->qty);
        $vat = $content->sum(fn ($item) => $item->vat * $item->qty);
        $discount = $content->sum(fn ($item) => $item->discountValue ?? 0);
        $total = $content->sum(fn ($item) => $item->totalPrice * $item->qty);

        render(<<<'HTML'
            <div class="mx-2 mt-1">
                <span class="text-gray font-bold"> Totals</span>
            </div>
        HTML);

        $rows = [
            ['Subtotal', $subtotal],
            ['VAT', $vat],
            ['Discount', $discount],
            ['Total', $total],
        ];

        foreach ($rows as [$label, $value]) {
            $formatted = number_format((float) $value, 2);
            $dots = str_repeat('.', max(1, 38 - mb_strlen($label)));
            $colorClass = $label === 'Total' ? 'text-green-400 font-bold' : 'text-white';

            render(<<<HTML
                <div class="mx-4">
                    <span class="text-gray">{$label}</span>
                    <span class="text-gray"> {$dots} </span>
                    <span class="{$colorClass}">{$formatted}</span>
                </div>
            HTML);
        }
    }

    private function renderNotFound(string $identifier, string $instance): void
    {
        $safeId = e($identifier);
        $safeInstance = e($instance);

        render(<<<HTML
            <div class="mx-2 my-1">
                <span class="px-1 bg-red-500 text-white font-bold"> NOT FOUND </span>
                <span class="ml-1 text-red-400">No cart found for identifier "{$safeId}" (instance: {$safeInstance})</span>
            </div>
        HTML);
    }

    private function renderDeserializeError(): void
    {
        render(<<<'HTML'
            <div class="mx-2 my-1">
                <span class="px-1 bg-red-500 text-white font-bold"> ERROR </span>
                <span class="ml-1 text-red-400">Failed to deserialize cart content. Use --raw to view raw data.</span>
            </div>
        HTML);
    }
}
