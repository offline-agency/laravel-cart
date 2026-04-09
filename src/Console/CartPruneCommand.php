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
        {--dry-run : Show how many carts would be deleted without actually deleting}
        {--force : Skip confirmation prompt}';

    protected $description = 'Delete stored carts older than a given threshold';

    use InteractsWithCartDatabase;

    public function handle(): int
    {
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
            if (! $this->confirm("This will delete {$count} cart(s) older than {$hours} hours. Continue?")) {
                $this->renderCancelled();

                return self::SUCCESS;
            }
        }

        $deleted = $query->delete();

        $this->renderPruned($deleted, $hours);

        return self::SUCCESS;
    }

    private function renderDryRun(int $count, int $hours, array $breakdown): void
    {
        render(<<<HTML
            <div class="mx-2 my-1">
                <span class="px-1 bg-yellow-500 text-black font-bold"> DRY RUN </span>
                <span class="ml-1">Found {$count} carts older than {$hours} hours.</span>
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

        render(<<<'HTML'
            <div class="mx-2 mt-1">
                <span class="text-gray">No records deleted.</span>
            </div>
        HTML);
    }

    private function renderPruned(int $deleted, int $hours): void
    {
        render(<<<HTML
            <div class="mx-2 my-1">
                <span class="px-1 bg-green-500 text-black font-bold"> PRUNED </span>
                <span class="ml-1">Deleted {$deleted} cart(s) older than {$hours} hours.</span>
            </div>
        HTML);
    }

    private function renderEmpty(int $hours): void
    {
        render(<<<HTML
            <div class="mx-2 my-1">
                <span class="px-1 bg-yellow-500 text-black font-bold"> EMPTY </span>
                <span class="ml-1 text-yellow-400">No carts older than {$hours} hours found.</span>
            </div>
        HTML);
    }

    private function renderCancelled(): void
    {
        render(<<<'HTML'
            <div class="mx-2 my-1">
                <span class="px-1 bg-yellow-500 text-black font-bold"> CANCELLED </span>
                <span class="ml-1 text-yellow-400">Operation cancelled.</span>
            </div>
        HTML);
    }
}
