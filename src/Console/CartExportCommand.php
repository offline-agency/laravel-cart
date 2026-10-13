<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Console;

use Carbon\Carbon;
use Illuminate\Console\Command;
use OfflineAgency\LaravelCart\Console\Concerns\InteractsWithCartDatabase;

use function Termwind\render;

class CartExportCommand extends Command
{
    protected $signature = 'cart:export
        {--path=storage/app/cart-export.json : Output file path}
        {--instance= : Only export carts for a specific instance}
        {--since= : Only export carts created after this date (Y-m-d)}
        {--pretty : Pretty-print the JSON output}';

    protected $description = 'Export stored carts to a JSON file';

    use InteractsWithCartDatabase;

    public function handle(): int
    {
        $path = $this->option('path');
        $instance = $this->option('instance');
        $since = $this->option('since');

        if (! str_starts_with($path, '/')) {
            $path = base_path($path);
        }

        $dir = dirname($path);
        if (! is_dir($dir)) {
            $this->renderError(__('laravel-cart::cart.command_export_dir_not_found', ['dir' => $dir]));

            return self::FAILURE;
        }

        $query = $this->cartQuery();

        if ($instance !== null) {
            $query->where('instance', $instance);
        }

        if ($since !== null) {
            try {
                $sinceDate = Carbon::parse($since);
                $query->where('created_at', '>=', $sinceDate);
            } catch (\Throwable) {
                $this->renderError(__('laravel-cart::cart.command_export_invalid_date', ['date' => $since]));

                return self::FAILURE;
            }
        }

        $totalCount = (clone $query)->count();

        if ($totalCount === 0) {
            $this->renderEmpty();

            return self::SUCCESS;
        }

        if ($totalCount > 5000) {
            $this->renderWarning(__('laravel-cart::cart.command_export_warning', ['limit' => $totalCount]));
        }

        $this->renderExporting($totalCount);

        $carts = [];

        (clone $query)->orderBy('identifier')->chunk(100, function ($rows) use (&$carts): void {
            foreach ($rows as $row) {
                $content = $this->deserializeContent($row->content);

                $cart = [
                    'identifier' => $row->identifier,
                    'instance' => $row->instance,
                    'items' => $content->isNotEmpty()
                        ? $content->map(fn ($item) => $item->toArray())->values()->toArray()
                        : null,
                    'coupons' => $this->decodeCoupons($row->coupons ?? null),
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ];

                if ($cart['items'] === null && $row->content !== '' && $row->content !== serialize(collect())) {
                    $cart['error'] = 'Failed to deserialize';
                }

                $carts[] = $cart;
            }
        });

        $export = [
            'exported_at' => Carbon::now()->toIso8601String(),
            'total_carts' => $totalCount,
            'carts' => $carts,
        ];

        $flags = JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE;
        if ($this->option('pretty')) {
            $flags |= JSON_PRETTY_PRINT;
        }

        file_put_contents($path, json_encode($export, $flags));

        $size = $this->formatFileSize((int) filesize($path));

        $this->renderDone(e(__('laravel-cart::cart.command_export_done', ['path' => $path, 'size' => $size])));

        return self::SUCCESS;
    }

    private function renderExporting(int $count): void
    {
        $formatted = number_format($count);
        $dots = str_repeat('.', max(1, 25));

        render(<<<HTML
            <div class="mx-2 my-1">
                <span class="px-1 bg-blue-500 text-white font-bold"> EXPORT </span>
                <span class="ml-1">Exporting carts...</span>
            </div>
            <div class="mx-4">
                <span class="text-gray">Processing</span>
                <span class="text-gray"> {$dots} </span>
                <span class="text-white font-bold">{$formatted} carts</span>
            </div>
        HTML);
    }

    private function renderDone(string $message): void
    {
        render(<<<HTML
            <div class="mx-2 my-1">
                <span class="px-1 bg-green-500 text-black font-bold"> DONE </span>
                <span class="ml-1">{$message}</span>
            </div>
        HTML);
    }

    private function renderEmpty(): void
    {
        $message = e(__('laravel-cart::cart.command_export_empty'));

        render(<<<HTML
            <div class="mx-2 my-1">
                <span class="px-1 bg-yellow-500 text-black font-bold"> EMPTY </span>
                <span class="ml-1 text-yellow-400">{$message}</span>
            </div>
        HTML);
    }

    private function renderError(string $message): void
    {
        $safe = e($message);

        render(<<<HTML
            <div class="mx-2 my-1">
                <span class="px-1 bg-red-500 text-white font-bold"> ERROR </span>
                <span class="ml-1 text-red-400">{$safe}</span>
            </div>
        HTML);
    }

    private function renderWarning(string $message): void
    {
        $safe = e($message);

        render(<<<HTML
            <div class="mx-2 my-1">
                <span class="px-1 bg-yellow-500 text-black font-bold"> WARN </span>
                <span class="ml-1 text-yellow-400">{$safe}</span>
            </div>
        HTML);
    }

    private function formatFileSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1).' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }
}
