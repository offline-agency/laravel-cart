<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use OfflineAgency\LaravelCart\Support\CartContentCodec;

function covMigrateCreateSessionsTable(): void
{
    DB::connection('testing')->getSchemaBuilder()->create('sessions', function (Blueprint $table): void {
        $table->string('id')->primary();
        $table->text('payload')->nullable();
        $table->integer('last_activity')->default(0);
        $table->string('user_id')->nullable();
    });
}

function covMigrateInsertSession(string $id, ?string $payload, ?string $userId = null): void
{
    DB::connection('testing')->table('sessions')->insert([
        'id' => $id,
        'payload' => $payload,
        'user_id' => $userId,
    ]);
}

function covMigrateEncode(mixed $payload): string
{
    return base64_encode(serialize($payload));
}

function covMigrateConfirmQuestion(int $count): string
{
    return __('laravel-cart::cart.command_migrate_confirm', ['count' => $count]);
}

/**
 * Per-process directory for the file session driver tests.
 */
function covMigrateSessionPath(): string
{
    return sys_get_temp_dir().'/cov-cart-sessions-'.getmypid();
}

/**
 * The session content copied into the legacy cart table, as stored (the
 * fixtures use placeholder values, not cart items).
 *
 * @return array<array-key, mixed>
 */
function covMigrateStoredItems(string $identifier): array
{
    $content = DB::table('cart')->where('identifier', $identifier)->value('content');
    $payload = is_string($content) ? base64_decode(substr($content, strlen(CartContentCodec::PREFIX)), true) : false;
    $items = is_string($payload) ? unserialize($payload) : null;

    return $items instanceof Collection ? $items->all() : [];
}

beforeEach(function (): void {
    $this->artisan('migrate', ['--database' => 'testing']);
});

// ─── database session driver ────────────────────────────────────────────────

describe('database sessions', function (): void {
    beforeEach(function (): void {
        config([
            'session.driver' => 'database',
            'session.table' => 'sessions',
            'session.connection' => 'testing',
        ]);

        covMigrateCreateSessionsTable();
    });

    it('reports when there are no sessions', function (): void {
        $this->artisan('cart:migrate-driver', ['--from' => 'session', '--to' => 'database'])
            ->assertExitCode(0);

        expect(DB::table('cart')->count())->toBe(0);
    });

    it('does not write on dry run', function (): void {
        covMigrateInsertSession('s1', covMigrateEncode(['cart.default' => collect(['a'])]));

        $this->artisan('cart:migrate-driver', ['--from' => 'session', '--to' => 'database', '--dry-run' => true])
            ->assertExitCode(0);

        expect(DB::table('cart')->count())->toBe(0);
    });

    it('can be cancelled at the confirmation prompt', function (): void {
        covMigrateInsertSession('s1', covMigrateEncode(['cart.default' => collect(['a'])]));

        $this->artisan('cart:migrate-driver', ['--from' => 'session', '--to' => 'database'])
            ->expectsConfirmation(covMigrateConfirmQuestion(1), 'no')
            ->assertExitCode(0);

        expect(DB::table('cart')->count())->toBe(0);
    });

    it('migrates cart keys after confirmation and skips unusable sessions', function (): void {
        // Migrated: guest session, identifier falls back to the session id.
        covMigrateInsertSession('guest-session', covMigrateEncode([
            'cart.default' => collect(['item-a']),
            'cart.wishlist' => null,
            '_token' => 'abc',
        ]));
        // Migrated: authenticated session, identifier is the user id.
        covMigrateInsertSession('user-session', covMigrateEncode(['cart.default' => collect(['item-b'])]), '7');
        // Already stored cart is left untouched.
        covMigrateInsertSession('existing', covMigrateEncode(['cart.default' => collect(['item-c'])]));
        // Skipped: invalid base64.
        covMigrateInsertSession('bad-base64', '!!!not-base64!!!');
        // Skipped: decoded payload is not an array.
        covMigrateInsertSession('not-array', covMigrateEncode('a string'));
        // Skipped: no cart keys.
        covMigrateInsertSession('no-cart', covMigrateEncode(['_token' => 'abc']));
        // Failed: null payload triggers a TypeError in the decoder.
        covMigrateInsertSession('null-payload', null);

        DB::table('cart')->insert([
            'identifier' => 'existing',
            'instance' => 'default',
            'content' => CartContentCodec::encode(collect(['original'])),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('cart:migrate-driver', ['--from' => 'session', '--to' => 'database', '--chunk' => 2])
            ->expectsConfirmation(covMigrateConfirmQuestion(7), 'yes')
            ->assertExitCode(0);

        expect(DB::table('cart')->count())->toBe(3)
            ->and(covMigrateStoredItems('guest-session'))->toBe(['item-a'])
            ->and(covMigrateStoredItems('7'))->toBe(['item-b'])
            ->and(covMigrateStoredItems('existing'))->toBe(['original'])
            ->and(DB::table('cart')->where('instance', 'wishlist')->exists())->toBeFalse();
    });

    it('reports carts that fail to be written', function (): void {
        covMigrateInsertSession('s1', covMigrateEncode(['cart.default' => collect(['a'])]));

        DB::connection('testing')->getSchemaBuilder()->drop('cart');

        $this->artisan('cart:migrate-driver', ['--from' => 'session', '--to' => 'database', '--force' => true])
            ->assertExitCode(0);
    });
});

// ─── file session driver ────────────────────────────────────────────────────

describe('file sessions', function (): void {
    beforeEach(function (): void {
        File::ensureDirectoryExists(covMigrateSessionPath());

        config([
            'session.driver' => 'file',
            'session.files' => covMigrateSessionPath(),
        ]);
    });

    afterEach(function (): void {
        File::deleteDirectory(covMigrateSessionPath());
    });

    it('fails when the session directory does not exist', function (): void {
        config(['session.files' => covMigrateSessionPath().'/missing']);

        $this->artisan('cart:migrate-driver', ['--from' => 'session', '--to' => 'database'])
            ->assertExitCode(1);
    });

    it('reports when there are no session files', function (): void {
        $this->artisan('cart:migrate-driver', ['--from' => 'session', '--to' => 'database'])
            ->assertExitCode(0);
    });

    it('does not write on dry run', function (): void {
        file_put_contents(covMigrateSessionPath().'/s1', serialize(['cart.default' => collect(['a'])]));

        $this->artisan('cart:migrate-driver', ['--from' => 'session', '--to' => 'database', '--dry-run' => true])
            ->assertExitCode(0);

        expect(DB::table('cart')->count())->toBe(0);
    });

    it('can be cancelled at the confirmation prompt', function (): void {
        file_put_contents(covMigrateSessionPath().'/s1', serialize(['cart.default' => collect(['a'])]));

        $this->artisan('cart:migrate-driver', ['--from' => 'session', '--to' => 'database'])
            ->expectsConfirmation(covMigrateConfirmQuestion(1), 'no')
            ->assertExitCode(0);

        expect(DB::table('cart')->count())->toBe(0);
    });

    it('migrates cart keys after confirmation and skips unusable files', function (): void {
        file_put_contents(covMigrateSessionPath().'/migrate-me', serialize([
            'cart.default' => collect(['item-a']),
            'cart.wishlist' => null,
        ]));
        file_put_contents(covMigrateSessionPath().'/existing', serialize(['cart.default' => collect(['item-c'])]));
        file_put_contents(covMigrateSessionPath().'/not-array', 'garbage');
        file_put_contents(covMigrateSessionPath().'/no-cart', serialize(['_token' => 'abc']));
        // A sub-directory cannot be read as a file and is reported as a failure.
        mkdir(covMigrateSessionPath().'/a-directory');

        DB::table('cart')->insert([
            'identifier' => 'existing',
            'instance' => 'default',
            'content' => CartContentCodec::encode(collect(['original'])),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('cart:migrate-driver', ['--from' => 'session', '--to' => 'database'])
            ->expectsConfirmation(covMigrateConfirmQuestion(5), 'yes')
            ->assertExitCode(0);

        expect(DB::table('cart')->count())->toBe(2)
            ->and(covMigrateStoredItems('migrate-me'))->toBe(['item-a'])
            ->and(covMigrateStoredItems('existing'))->toBe(['original']);
    });

    it('skips files that cannot be read', function (): void {
        symlink(covMigrateSessionPath().'/does-not-exist', covMigrateSessionPath().'/dangling');

        $previous = error_reporting(E_ALL & ~E_WARNING);

        try {
            $this->artisan('cart:migrate-driver', ['--from' => 'session', '--to' => 'database', '--force' => true])
                ->assertExitCode(0);
        } finally {
            error_reporting($previous);
        }

        expect(DB::table('cart')->count())->toBe(0);
    });

    it('reports carts that fail to be written', function (): void {
        file_put_contents(covMigrateSessionPath().'/s1', serialize(['cart.default' => collect(['a'])]));

        DB::connection('testing')->getSchemaBuilder()->drop('cart');

        $this->artisan('cart:migrate-driver', ['--from' => 'session', '--to' => 'database', '--force' => true])
            ->assertExitCode(0);
    });
});

// ─── option validation ──────────────────────────────────────────────────────

it('fails when source and target drivers are the same', function (): void {
    $this->artisan('cart:migrate-driver', ['--from' => 'session', '--to' => 'session'])
        ->assertExitCode(1);
});

it('fails for unknown driver names', function (): void {
    $this->artisan('cart:migrate-driver', ['--from' => 'session', '--to' => 'redis'])
        ->assertExitCode(1);
});

it('refuses to migrate from database to session', function (): void {
    $this->artisan('cart:migrate-driver', ['--from' => 'database', '--to' => 'session'])
        ->assertExitCode(1);
});

it('refuses unsupported session drivers', function (): void {
    config(['session.driver' => 'redis']);

    $this->artisan('cart:migrate-driver', ['--from' => 'session', '--to' => 'database'])
        ->assertExitCode(1);
});
