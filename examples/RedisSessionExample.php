<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Examples;

use Illuminate\Cache\CacheManager;
use Illuminate\Cache\RedisStore;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Application;
use Illuminate\Session\SessionManager;
use Illuminate\Session\Store;
use LogicException;
use OfflineAgency\LaravelCart\Cart;
use OfflineAgency\LaravelCart\Storage\SessionStorage;

/** Optional real-Redis recipe check, requiring a disposable local Redis server. */
final class RedisSessionExample
{
    public static function run(Application $app, int $port): void
    {
        config(['session.driver' => 'redis', 'session.connection' => 'cart_recipe_test',
            'session.store' => null, 'session.lifetime' => 120, 'session.block_store' => 'redis',
            'database.redis.client' => 'phpredis', 'database.redis.cart_recipe_test' => [
                'host' => '127.0.0.1', 'port' => $port, 'database' => 0,
            ], 'cache.prefix' => 'cart_recipe_', 'cache.stores.redis' => [
                'driver' => 'redis', 'connection' => 'cart_recipe_test', 'lock_connection' => 'cart_recipe_test',
            ]]);
        $manager = new SessionManager($app);
        $store = $manager->driver();
        if (! $store instanceof Store) {
            throw new LogicException('A Laravel session Store is required.');
        }
        $store->start();
        $id = $store->getId();
        $cart = new Cart(new SessionStorage($manager), $app->make(Dispatcher::class));
        $cart->add(id: 1, name: 'Redis session', qty: 2, price: 10.0);
        $store->save();
        $second = new SessionManager($app);
        $secondStore = $second->driver();
        if (! $secondStore instanceof Store) {
            throw new LogicException('A Laravel session Store is required.');
        }
        $secondStore->setId($id);
        $secondStore->start();
        $restored = new Cart(new SessionStorage($second), $app->make(Dispatcher::class));
        $cache = $app->make(CacheManager::class)->store('redis')->getStore();
        if (! $cache instanceof RedisStore || $second->blockDriver() !== 'redis') {
            throw new LogicException('The configured Redis blocking store is required.');
        }
        $firstLock = $cache->lock('recipe-'.$id, 10);
        $secondLock = $cache->lock('recipe-'.$id, 10);
        $afterRelease = $cache->lock('recipe-'.$id, 10, 'retry-'.$id);
        try {
            if ($restored->total() !== 20.0 || ! $firstLock->get() || $secondLock->get()) {
                throw new LogicException('Redis session persistence or lock exclusion failed.');
            }
            $firstLock->release();
            if (! $afterRelease->get()) {
                throw new LogicException('Redis lock was not available after release.');
            }
        } finally {
            $firstLock->release();
            $secondLock->release();
            $afterRelease->release();
            $store->getHandler()->destroy($id);
        }
    }
}
