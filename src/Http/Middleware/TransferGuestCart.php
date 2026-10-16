<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use OfflineAgency\LaravelCart\Contracts\CartStorage;
use OfflineAgency\LaravelCart\Events\GuestCartTransferFailed;
use OfflineAgency\LaravelCart\Events\GuestCartTransferred;
use OfflineAgency\LaravelCart\Exceptions\GuestCartTransferException;
use OfflineAgency\LaravelCart\Storage\DatabaseStorage;
use Symfony\Component\HttpFoundation\Response;

/** Place after StartSession and before the login action in the web stack. */
final class TransferGuestCart
{
    private const string PENDING = 'laravel_cart.pending_guest_transfer';

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('cart.guest_transfer.enabled', false)) {
            return $next($request);
        }
        $guest = auth()->guest() ? $request->session()->getId() : null;
        $response = $next($request);
        $userId = auth()->id();
        if ($userId === null) {
            return $response;
        }
        $pending = $request->session()->get(self::PENDING);
        if ($guest !== null) {
            $pending = ['guest' => $guest, 'user' => $userId];
            $request->session()->put(self::PENDING, $pending);
        }
        if (! is_array($pending) || ($pending['user'] ?? null) !== $userId || ! is_string($pending['guest'] ?? null)) {
            return $response;
        }
        $instances = config('cart.guest_transfer.instances', ['default']);
        if (! is_array($instances) || ! array_is_list($instances)) {
            throw new GuestCartTransferException('cart.guest_transfer.instances must be a list of non-empty instance names.');
        }
        $allowed = [];
        foreach ($instances as $name) {
            if (! is_string($name) || $name === '') {
                throw new GuestCartTransferException('Transfer instance names must be non-empty strings.');
            }
            $allowed[] = $name;
        }
        $storage = app(CartStorage::class);
        if (! $storage instanceof DatabaseStorage) {
            throw new GuestCartTransferException('Guest transfer requires the database cart driver.');
        }
        try {
            $transferred = $storage->transferGuest($pending['guest'], $allowed);
        } catch (GuestCartTransferException $exception) {
            event(new GuestCartTransferFailed($userId, $exception));
            report($exception);

            return $response;
        }
        $storage->afterCommit(function () use ($request, $pending): void {
            if ($request->session()->get(self::PENDING) === $pending) {
                $request->session()->forget(self::PENDING);
            }
        });
        if ($transferred) {
            $storage->afterCommit(fn () => event(new GuestCartTransferred($userId, $allowed)));
        }

        return $response;
    }
}
