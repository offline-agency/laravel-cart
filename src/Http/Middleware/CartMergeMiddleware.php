<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use OfflineAgency\LaravelCart\Cart;
use OfflineAgency\LaravelCart\Support\Coerce;
use Symfony\Component\HttpFoundation\Response;

class CartMergeMiddleware
{
    public function __construct(
        private readonly Cart $cart,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! config('cart.middleware.merge_on_login', false)) {
            return $response;
        }

        $user = $request->user();
        if ($user === null) {
            return $response;
        }

        $sessionKey = 'cart_guest_merged_'.session()->getId();
        if (session()->has($sessionKey)) {
            return $response;
        }

        // Attempt to restore the user's stored cart and merge with current session cart
        try {
            $this->cart->restore(
                Coerce::key($user->getAuthIdentifier()),
                mergeOnRestore: true,
                keepStored: true,
            );
        } catch (\Throwable) {
            // No stored cart or restore failed — silently continue
        }

        session()->put($sessionKey, true);

        return $response;
    }
}
