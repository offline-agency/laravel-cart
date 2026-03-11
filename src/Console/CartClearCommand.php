<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

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

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $table = config('cart.database.table', 'cart');
        $instance = $this->option('instance');

        if (! $this->option('force') && ! $this->confirmAction($instance)) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        $query = DB::connection(config('cart.database.connection') ?? config('database.default'))
            ->table($table);

        if ($instance !== null) {
            $query->where('instance', $instance);
        }

        $deleted = $query->delete();

        $this->info($instance !== null
            ? "Cleared {$deleted} cart record(s) for instance '{$instance}'."
            : "Cleared {$deleted} cart record(s) from the '{$table}' table."
        );

        return self::SUCCESS;
    }

    private function confirmAction(?string $instance): bool
    {
        $message = $instance !== null
            ? "This will delete all stored cart records for instance '{$instance}'. Continue?"
            : 'This will delete ALL stored cart records from the database. Continue?';

        return $this->confirm($message);
    }
}
