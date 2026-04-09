<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use OfflineAgency\LaravelCart\Console\Concerns\InteractsWithCartDatabase;

use function Termwind\{render};

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
            $this->renderError('Source and target drivers must be different.');

            return self::FAILURE;
        }

        $validDrivers = ['session', 'database'];
        if (! in_array($from, $validDrivers, true) || ! in_array($to, $validDrivers, true)) {
            $this->renderError('Valid drivers are: session, database.');

            return self::FAILURE;
        }

        $this->renderMigrateHeader($from, $to);

        if ($from === 'database' && $to === 'session') {
            $this->renderUnsupported(
                'Cannot migrate from database to session via CLI. '
                .'Session writes require an active HTTP request. '
                .'Use Cart::restore() within an authenticated request context instead.'
            );

            return self::FAILURE;
        }

        if ($from === 'session' && $to === 'database') {
            return $this->migrateSessionToDatabase($chunk);
        }

        return self::SUCCESS;
    }

    private function migrateSessionToDatabase(int $chunk): int
    {
        $sessionDriver = config('session.driver');

        if ($sessionDriver !== 'database' && $sessionDriver !== 'file') {
            $this->renderUnsupported(
                "Session driver '{$sessionDriver}' is not supported for CLI migration. "
                .'Only "database" and "file" session drivers can be read from the CLI. '
                .'Use Cart::store() within an authenticated request context for other drivers.'
            );

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
            if (! $this->confirm("Migrate cart data from {$total} session(s) to the database? Continue?")) {
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
            $this->renderError("Session files directory not found: {$path}");

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
            if (! $this->confirm("Migrate cart data from {$total} session file(s) to the database? Continue?")) {
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
        render(<<<HTML
            <div class="mx-2 my-1">
                <span class="px-1 bg-green-500 text-black font-bold"> DONE </span>
                <span class="ml-1">
                    Migrated <span class="font-bold text-white">{$migrated}</span> carts.
                    Failed: <span class="font-bold text-red-400">{$failed}</span>.
                    Skipped: <span class="font-bold text-yellow-400">{$skipped}</span>.
                </span>
            </div>
        HTML);

        if ($failed > 0) {
            render(<<<HTML
                <div class="mx-2">
                    <span class="px-1 bg-yellow-500 text-black font-bold"> WARN </span>
                    <span class="ml-1 text-yellow-400">{$failed} carts failed to migrate (see above for details).</span>
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
        render(<<<HTML
            <div class="mx-2 my-1">
                <span class="px-1 bg-yellow-500 text-black font-bold"> DRY RUN </span>
                <span class="ml-1">Found {$count} sessions to scan. No records written.</span>
            </div>
        HTML);
    }

    private function renderEmpty(): void
    {
        render(<<<'HTML'
            <div class="mx-2 my-1">
                <span class="px-1 bg-yellow-500 text-black font-bold"> EMPTY </span>
                <span class="ml-1 text-yellow-400">No sessions found.</span>
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
        render(<<<'HTML'
            <div class="mx-2 my-1">
                <span class="px-1 bg-yellow-500 text-black font-bold"> CANCELLED </span>
                <span class="ml-1 text-yellow-400">Operation cancelled.</span>
            </div>
        HTML);
    }
}
