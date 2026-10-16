<?php

declare(strict_types=1);

use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Events\QueryExecuted;
use OfflineAgency\LaravelCart\Cart;
use OfflineAgency\LaravelCart\Events\CartItemAdded;
use OfflineAgency\LaravelCart\Storage\DatabaseStorage;

it('serializes independent requests from locked read through write', function (bool $sameRow): void {
    if (! in_array(getenv('CART_TEST_DB_DRIVER'), ['pgsql', 'mysql'], true)) {
        $this->markTestSkipped('Requires a disposable PostgreSQL/MySQL server and pcntl.');
    }
    $this->artisan('migrate', ['--database' => 'testing']);
    $database = app(DatabaseManager::class);
    $cart = new Cart(new DatabaseStorage($database), app('events'));
    $cart->add(id: 1, name: 'One', qty: 1, price: 10.0);
    // Never inherit an open PDO socket across fork.
    $database->purge('testing');
    $pairs = [];
    $children = [];
    for ($worker = 0; $worker < 2; $worker++) {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        if ($pair === false) {
            throw new RuntimeException('Cannot create synchronization sockets.');
        }
        $pid = pcntl_fork();
        if ($pid === -1) {
            throw new RuntimeException('Cannot fork test worker.');
        }
        if ($pid === 0) {
            fclose($pair[0]);
            $channel = $pair[1];
            stream_set_timeout($channel, 10);
            try {
                $database->purge('testing');
                $connection = $database->connection('testing');
                $connection->statement(getenv('CART_TEST_DB_DRIVER') === 'pgsql' ? "SET lock_timeout = '5s'" : 'SET innodb_lock_wait_timeout = 5');
                $held = false;
                $connection->listen(function (QueryExecuted $query) use ($worker, $channel, &$held): void {
                    if ($worker === 0 && ! $held && str_contains(strtolower($query->sql), 'for update')) {
                        $held = true;
                        fwrite($channel, "locked\n");
                        if (trim((string) fgets($channel)) !== 'release') {
                            throw new RuntimeException('Lock release synchronization timed out.');
                        }
                    }
                });
                $events = 0;
                app('events')->listen(CartItemAdded::class, function () use (&$events): void {
                    $events++;
                });
                $requestCart = new Cart(new DatabaseStorage($database), app('events'));
                fwrite($channel, "started\n");
                $requestCart->add(id: $sameRow || $worker === 0 ? 1 : 2, name: 'One', qty: 1, price: 10.0);
                fwrite($channel, 'done:'.$events."\n");
                fclose($channel);
                exit(0);
            } catch (Throwable $exception) {
                fwrite($channel, 'error:'.$exception->getMessage()."\n");
                exit(1);
            }
        }
        fclose($pair[1]);
        stream_set_timeout($pair[0], 10);
        $pairs[] = $pair[0];
        $children[] = $pid;
        if ($worker === 0) {
            expect(trim((string) fgets($pair[0])))->toBe('started');
            expect(trim((string) fgets($pair[0])))->toBe('locked');
        }
    }
    try {
        expect(trim((string) fgets($pairs[1])))->toBe('started');
        $read = [$pairs[1]];
        $write = $except = null;
        // B has started while A holds the row lock, and cannot complete yet.
        expect(stream_select($read, $write, $except, 0, 200000))->toBe(0);
        fwrite($pairs[0], "release\n");
        foreach ($pairs as $channel) {
            expect(trim((string) fgets($channel)))->toBe('done:1');
        }
    } finally {
        foreach ($pairs as $channel) {
            fclose($channel);
        }
        foreach ($children as $pid) {
            $status = 0;
            pcntl_waitpid($pid, $status);
            if (! is_int($status)) {
                throw new RuntimeException('Missing child exit status.');
            }
            expect(pcntl_wexitstatus($status))->toBe(0);
        }
        $database->purge('testing');
    }
    $final = new Cart(new DatabaseStorage($database), app('events'));
    expect($final->count())->toBe(3)->and($final->uniqueCount())->toBe($sameRow ? 1 : 2);
    expect($final->content()->first()?->qty)->toBe($sameRow ? 3 : 2);
})->with([true, false])->group('concurrency');
