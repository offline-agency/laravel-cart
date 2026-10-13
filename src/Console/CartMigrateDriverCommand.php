<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use OfflineAgency\LaravelCart\Console\Concerns\InteractsWithCartDatabase;

use function Termwind\render;

class CartMigrateDriverCommand extends Command
{
    protected $signature = 'cart:migrate-driver
        {--from=session : Source driver (session or database)}
        {--to=database : Target driver (session or database)}
        {--dry-run : Show what would be migrated without writing}
        {--force : Skip confirmation prompt}
        {--chunk=100 : Process this many carts at a time}';

    protected $description = 'Migrate stored carts between storage drivers';

    use InteractsWithCartDatabase;

    public function handle(): int
    {
        $from = $this->option('from');
        $to = $this->option('to');
        $chunk = (int) $this->option('chunk');

        if ($from === $to) {
            $this->renderError(__('laravel-cart::cart.command_migrate_same_driver'));

            return self::FAILURE;
        }

        $validDrivers = ['session', 'database'];
        if (! in_array($from, $validDrivers, true) || ! in_array($to, $validDrivers, true)) {
            $this->renderError(__('laravel-cart::cart.command_migrate_invalid_driver'));

            return self::FAILURE;
        }

        $this->renderMigrateHeader($from, $to);

        if ($from === 'database' && $to === 'session') {
            $this->renderUnsupported(__('laravel-cart::cart.command_migrate_no_db_to_session'));

            return self::FAILURE;
        }

        return $this->migrateSessionToDatabase($chunk);
    }

    private function migrateSessionToDatabase(int $chunk): int
    {
        $sessionDriver = config('session.driver');

        if ($sessionDriver !== 'database' && $sessionDriver !== 'file') {
            $this->renderUnsupported(__('laravel-cart::cart.command_migrate_unsupported_session', ['driver' => $sessionDriver]));

            return self::FAILURE;
        }

        if ($sessionDriver === 'database') {
            return $this->migrateFromDatabaseSessions($chunk);
        }

        return $this->migrateFromFileSessions($chunk);
    }

    private function migrateFromDatabaseSessions(int $chunk): int
    {
        $sessionsTable = config('session.table', 'sessions');
        $sessionConnection = config('session.connection') ?? config('database.default');

        $total = DB::connection($sessionConnection)
            ->table($sessionsTable)
            ->count();

        $this->renderScanResult($total);

        if ($total === 0) {
            $this->renderEmpty();

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->renderDryRun($total);

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            if (! $this->confirm(__('laravel-cart::cart.command_migrate_confirm', ['count' => $total]))) {
                $this->renderCancelled();

                return self::SUCCESS;
            }
        }

        $progressBar = $this->output->createProgressBar($total);
        $progressBar->start();

        $migrated = 0;
        $failed = 0;
        $skipped = 0;

        DB::connection($sessionConnection)
            ->table($sessionsTable)
            ->orderBy('id')
            ->chunk($chunk, function ($sessions) use (&$migrated, &$failed, &$skipped, $progressBar): void {
                foreach ($sessions as $session) {
                    $progressBar->advance();

                    try {
                        $payload = $this->decodeSessionPayload($session->payload);
                        if ($payload === null) {
                            $skipped++;

                            continue;
                        }

                        $cartKeys = array_filter(
                            array_keys($payload),
                            fn ($key) => str_starts_with($key, 'cart.')
                        );

                        if (empty($cartKeys)) {
                            $skipped++;

                            continue;
                        }

                        $identifier = $session->user_id ?? $session->id;

                        foreach ($cartKeys as $cartKey) {
                            $instance = str_replace('cart.', '', $cartKey);
                            $content = $payload[$cartKey] ?? null;

                            if ($content === null) {
                                continue;
                            }

                            try {
                                DB::transaction(function () use ($identifier, $instance, $content): void {
                                    $exists = $this->cartQuery()
                                        ->where('identifier', $identifier)
                                        ->where('instance', $instance)
                                        ->exists();

                                    if ($exists) {
                                        return;
                                    }

                                    $couponsKey = "cart_coupons.{$instance}";
                                    $coupons = null;

                                    $this->cartQuery()->insert([
                                        'identifier' => $identifier,
                                        'instance' => $instance,
                                        'content' => serialize($content),
                                        'coupons' => $coupons,
                                        'created_at' => now(),
                                        'updated_at' => now(),
                                    ]);
                                });

                                $migrated++;
                            } catch (\Throwable $e) {
                                $this->renderCartError((string) $identifier, $e->getMessage());
                                $failed++;
                            }
                        }
                    } catch (\Throwable $e) {
                        $this->renderCartError($session->id ?? 'unknown', $e->getMessage());
                        $failed++;
                    }
                }
            });

        $progressBar->finish();
        $this->output->newLine();

        $this->renderMigrateResult($migrated, $failed, $skipped);

        return self::SUCCESS;
    }

    private function migrateFromFileSessions(int $chunk): int
    {
        $path = config('session.files', storage_path('framework/sessions'));

        if (! is_dir($path)) {
            $this->renderError(__('laravel-cart::cart.command_migrate_session_dir_not_found', ['path' => $path]));

            return self::FAILURE;
        }

        $files = glob("{$path}/*");
        $total = count($files);

        $this->renderScanResult($total);

        if ($total === 0) {
            $this->renderEmpty();

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->renderDryRun($total);

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            if (! $this->confirm(__('laravel-cart::cart.command_migrate_confirm', ['count' => $total]))) {
                $this->renderCancelled();

                return self::SUCCESS;
            }
        }

        $progressBar = $this->output->createProgressBar($total);
        $progressBar->start();

        $migrated = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($files as $file) {
            $progressBar->advance();

            try {
                $raw = file_get_contents($file);
                if ($raw === false) {
                    $skipped++;

                    continue;
                }

                $payload = @unserialize($raw);
                if (! is_array($payload)) {
                    $skipped++;

                    continue;
                }

                $cartKeys = array_filter(
                    array_keys($payload),
                    fn ($key) => str_starts_with($key, 'cart.')
                );

                if (empty($cartKeys)) {
                    $skipped++;

                    continue;
                }

                $identifier = basename($file);

                foreach ($cartKeys as $cartKey) {
                    $instance = str_replace('cart.', '', $cartKey);
                    $content = $payload[$cartKey] ?? null;

                    if ($content === null) {
                        continue;
                    }

                    try {
                        DB::transaction(function () use ($identifier, $instance, $content): void {
                            $exists = $this->cartQuery()
                                ->where('identifier', $identifier)
                                ->where('instance', $instance)
                                ->exists();

                            if ($exists) {
                                return;
                            }

                            $this->cartQuery()->insert([
                                'identifier' => $identifier,
                                'instance' => $instance,
                                'content' => serialize($content),
                                'coupons' => null,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        });

                        $migrated++;
                    } catch (\Throwable $e) {
                        $this->renderCartError($identifier, $e->getMessage());
                        $failed++;
                    }
                }
            } catch (\Throwable $e) {
                $this->renderCartError(basename($file), $e->getMessage());
                $failed++;
            }
        }

        $progressBar->finish();
        $this->output->newLine();

        $this->renderMigrateResult($migrated, $failed, $skipped);

        return self::SUCCESS;
    }

    private function decodeSessionPayload(string $payload): ?array
    {
        $decoded = base64_decode($payload, true);
        if ($decoded === false) {
            return null;
        }

        $result = @unserialize($decoded);

        return is_array($result) ? $result : null;
    }

    private function renderMigrateHeader(string $from, string $to): void
    {
        $safeFrom = e($from);
        $safeTo = e($to);

        render(<<<HTML
            <div class="mx-2 my-1">
                <span class="px-1 bg-blue-500 text-white font-bold"> MIGRATE </span>
                <span class="ml-1">{$safeFrom} → {$safeTo}</span>
            </div>
        HTML);
    }

    private function renderScanResult(int $count): void
    {
        $dots = str_repeat('.', 30);

        render(<<<HTML
            <div class="mx-4">
                <span class="text-gray">Scanning source</span>
                <span class="text-gray"> {$dots} </span>
                <span class="text-white font-bold">{$count} sessions</span>
            </div>
        HTML);
    }

    private function renderMigrateResult(int $migrated, int $failed, int $skipped): void
    {
        $doneMessage = e(__('laravel-cart::cart.command_migrate_done', ['migrated' => $migrated, 'failed' => $failed, 'skipped' => $skipped]));

        render(<<<HTML
            <div class="mx-2 my-1">
                <span class="px-1 bg-green-500 text-black font-bold"> DONE </span>
                <span class="ml-1">{$doneMessage}</span>
            </div>
        HTML);

        if ($failed > 0) {
            $warnMessage = e(__('laravel-cart::cart.command_migrate_failed_warning', ['count' => $failed]));

            render(<<<HTML
                <div class="mx-2">
                    <span class="px-1 bg-yellow-500 text-black font-bold"> WARN </span>
                    <span class="ml-1 text-yellow-400">{$warnMessage}</span>
                </div>
            HTML);
        }
    }

    private function renderCartError(string $identifier, string $error): void
    {
        $safeId = e($identifier);
        $safeError = e($error);

        render(<<<HTML
            <div class="mx-4">
                <span class="text-red-400">✗</span>
                <span class="text-gray ml-1">{$safeId}:</span>
                <span class="text-red-400 ml-1">{$safeError}</span>
            </div>
        HTML);
    }

    private function renderDryRun(int $count): void
    {
        $message = e(__('laravel-cart::cart.command_migrate_dry_run', ['count' => $count]));

        render(<<<HTML
            <div class="mx-2 my-1">
                <span class="px-1 bg-yellow-500 text-black font-bold"> DRY RUN </span>
                <span class="ml-1">{$message}</span>
            </div>
        HTML);
    }

    private function renderEmpty(): void
    {
        $message = e(__('laravel-cart::cart.command_migrate_empty'));

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

    private function renderUnsupported(string $message): void
    {
        $safe = e($message);

        render(<<<HTML
            <div class="mx-2 my-1">
                <span class="px-1 bg-red-500 text-white font-bold"> UNSUPPORTED </span>
                <span class="ml-1 text-red-400">{$safe}</span>
            </div>
        HTML);
    }

    private function renderCancelled(): void
    {
        $message = e(__('laravel-cart::cart.command_clear_cancelled'));

        render(<<<HTML
            <div class="mx-2 my-1">
                <span class="px-1 bg-yellow-500 text-black font-bold"> CANCELLED </span>
                <span class="ml-1 text-yellow-400">{$message}</span>
            </div>
        HTML);
    }
}
