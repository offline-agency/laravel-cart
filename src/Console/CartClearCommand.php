<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Console;

use Illuminate\Console\Command;
use OfflineAgency\LaravelCart\Console\Concerns\InteractsWithCartDatabase;

use function Termwind\{render};

class CartClearCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cart:clear
                            {--force : Skip the confirmation prompt (required in non-interactive / production environments)}
                            {--instance= : Optional cart instance name to clear (defaults to clearing the entire table)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear all stored cart records from the database';

    use InteractsWithCartDatabase;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $table = $this->tableName();
        $instance = $this->option('instance');

        if (! $this->option('force') && ! $this->confirmAction($instance)) {
            $this->renderCancelled();

            return self::SUCCESS;
        }

        $query = $this->cartQuery();

        if ($instance !== null) {
            $query->where('instance', $instance);
        }

        $deleted = $query->delete();

        $this->renderCleared($deleted, $table, $instance);

        return self::SUCCESS;
    }

    private function confirmAction(?string $instance): bool
    {
        $message = $instance !== null
            ? "This will delete all stored cart records for instance '{$instance}'. Continue?"
            : 'This will delete ALL stored cart records from the database. Continue?';

        return $this->confirm($message);
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

    private function renderCleared(int $deleted, string $table, ?string $instance): void
    {
        $message = $instance !== null
            ? "Cleared {$deleted} cart record(s) for instance '".e($instance)."'."
            : "Cleared {$deleted} cart record(s) from the '".e($table)."' table.";

        render(<<<HTML
            <div class="mx-2 my-1">
                <span class="px-1 bg-green-500 text-black font-bold"> CLEARED </span>
                <span class="ml-1">{$message}</span>
            </div>
        HTML);
    }
}
