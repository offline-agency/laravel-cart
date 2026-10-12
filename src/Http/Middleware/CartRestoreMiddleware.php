<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use OfflineAgency\LaravelCart\Cart;
use Symfony\Component\HttpFoundation\Response;

class CartRestoreMiddleware
{
    public function __construct(
        private readonly Cart $cart,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('cart.middleware.auto_restore', false)) {
            return $next($request);
        }

        $user = $request->user();
        if ($user === null) {
            return $next($request);
        }

        if ($this->cart->isEmpty()) {
            try {
                $this->cart->restore(
                    $user->getAuthIdentifier(),
                    mergeOnRestore: true,
                    keepStored: true,
                );
            } catch (\Throwable) {
                // No stored cart — continue
            }
        }

        return $next($request);
    }
}
