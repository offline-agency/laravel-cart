<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Console;

use Carbon\Carbon;
use Illuminate\Console\Command;
use OfflineAgency\LaravelCart\Console\Concerns\InteractsWithCartDatabase;

use function Termwind\{render};

class CartPruneCommand extends Command
{
    protected $signature = 'cart:prune
        {--hours=24 : Delete carts not updated in this many hours}
        {--instance= : Only prune carts for a specific instance}
        {--expired-only : Only prune carts where expires_at has passed (normalized carts table)}
        {--dry-run : Show how many carts would be deleted without actually deleting}
        {--force : Skip confirmation prompt}';

    protected $description = 'Delete stored carts older than a given threshold';

    use InteractsWithCartDatabase;

    public function handle(): int
    {
        if ($this->option('expired-only')) {
            return $this->handleExpiredOnly();
        }

        $hours = (int) $this->option('hours');
        $instance = $this->option('instance');
        $cutoff = Carbon::now()->subHours($hours);

        $query = $this->cartQuery()
            ->where(function ($q) use ($cutoff): void {
                $q->where('updated_at', '<', $cutoff)
                    ->orWhere(function ($q2) use ($cutoff): void {
                        $q2->whereNull('updated_at')
                            ->where('created_at', '<', $cutoff);
                    });
            });

        if ($instance !== null) {
            $query->where('instance', $instance);
        }

        $count = (clone $query)->count();

        if ($count === 0) {
            $this->renderEmpty($hours);

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $breakdown = (clone $query)
                ->selectRaw('instance, count(*) as total')
                ->groupBy('instance')
                ->pluck('total', 'instance')
                ->toArray();

            $this->renderDryRun($count, $hours, $breakdown);

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            if (! $this->confirm(__('laravel-cart::cart.command_prune_confirm', ['count' => $count, 'hours' => $hours]))) {
                $this->renderCancelled();

                return self::SUCCESS;
            }
        }

        $deleted = $query->delete();

        $this->renderPruned($deleted, $hours);

        return self::SUCCESS;
    }

    private function handleExpiredOnly(): int
    {
        $cartsTable = (string) config('cart.database.carts_table', 'carts');
        $cartItemsTable = (string) config('cart.database.cart_items_table', 'cart_items');
        $connection = \Illuminate\Support\Facades\DB::connection($this->connectionName());

        $query = $connection->table($cartsTable)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now());

        $instance = $this->option('instance');
        if ($instance !== null) {
            $query->where('instance', $instance);
        }

        $count = (clone $query)->count();

        if ($count === 0) {
            $this->renderEmpty(0);

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->renderDryRun($count, 0, []);

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            if (! $this->confirm(__('laravel-cart::cart.command_prune_confirm', ['count' => $count, 'hours' => 0]))) {
                $this->renderCancelled();

                return self::SUCCESS;
            }
        }

        // Delete items first, then carts
        $cartIds = (clone $query)->pluck('id');
        $connection->table($cartItemsTable)->whereIn('cart_id', $cartIds)->delete();
        $deleted = $query->delete();

        $this->renderPruned($deleted, 0);

        return self::SUCCESS;
    }

    private function renderDryRun(int $count, int $hours, array $breakdown): void
    {
        $dryRunMsg = e(__('laravel-cart::cart.command_prune_dry_run', ['count' => $count, 'hours' => $hours]));

        render(<<<HTML
            <div class="mx-2 my-1">
                <span class="px-1 bg-yellow-500 text-black font-bold"> DRY RUN </span>
                <span class="ml-1">{$dryRunMsg}</span>
            </div>
        HTML);

        foreach ($breakdown as $inst => $instCount) {
            $safeInst = e((string) $inst);
            $dots = str_repeat('.', max(1, 35 - mb_strlen((string) $inst)));

            render(<<<HTML
                <div class="mx-4">
                    <span class="text-gray">{$safeInst}</span>
                    <span class="text-gray"> {$dots} </span>
                    <span class="text-white font-bold">{$instCount} carts</span>
                </div>
            HTML);
        }

        $noRecordsMsg = e(__('laravel-cart::cart.command_prune_no_records'));

        render(<<<HTML
            <div class="mx-2 mt-1">
                <span class="text-gray">{$noRecordsMsg}</span>
            </div>
        HTML);
    }

    private function renderPruned(int $deleted, int $hours): void
    {
        $prunedMsg = e(__('laravel-cart::cart.command_prune_done', ['count' => $deleted, 'hours' => $hours]));

        render(<<<HTML
            <div class="mx-2 my-1">
                <span class="px-1 bg-green-500 text-black font-bold"> PRUNED </span>
                <span class="ml-1">{$prunedMsg}</span>
            </div>
        HTML);
    }

    private function renderEmpty(int $hours): void
    {
        $emptyMsg = e(__('laravel-cart::cart.command_prune_empty'));

        render(<<<HTML
            <div class="mx-2 my-1">
                <span class="px-1 bg-yellow-500 text-black font-bold"> EMPTY </span>
                <span class="ml-1 text-yellow-400">{$emptyMsg}</span>
            </div>
        HTML);
    }

    private function renderCancelled(): void
    {
        $cancelledMsg = e(__('laravel-cart::cart.command_clear_cancelled'));

        render(<<<HTML
            <div class="mx-2 my-1">
                <span class="px-1 bg-yellow-500 text-black font-bold"> CANCELLED </span>
                <span class="ml-1 text-yellow-400">{$cancelledMsg}</span>
            </div>
        HTML);
    }
}
