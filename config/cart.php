<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cart database settings
    |--------------------------------------------------------------------------
    |
    | Here you can set the connection that the shopping cart should use when
    | storing and restoring a cart.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Storage driver
    |--------------------------------------------------------------------------
    |
    | The driver used to store the active cart. Supported: "session", "database".
    |
    */

    'storage' => [

        'driver' => env('CART_STORAGE_DRIVER', 'session'),

    ],

    'database' => [

        'connection' => null,

        'table' => 'cart',

        'carts_table' => 'carts',

        'cart_items_table' => 'cart_items',

    ],

    /*
    |--------------------------------------------------------------------------
    | User foreign key configuration
    |--------------------------------------------------------------------------
    |
    | Column (and its type) holding the authenticated owner of a cart in the
    | "carts" table used by the database storage driver. Guest carts are
    | stored under the nullable "session_id" column instead.
    |
    */

    'users' => [

        'foreign_key' => 'user_id',

        'foreign_key_type' => 'unsignedBigInteger',

        'table' => 'users',

    ],

    /*
    |--------------------------------------------------------------------------
    | Destroy the cart on user logout
    |--------------------------------------------------------------------------
    |
    | When this option is set to 'true' the cart will automatically
    | destroy all cart instances when the user logs out.
    |
    */

    'destroy_on_logout' => false,

    /*
    |--------------------------------------------------------------------------
    | Legacy string events
    |--------------------------------------------------------------------------
    |
    | Besides the typed event classes (CartItemAdded, CartStored, ...) the cart
    | dispatches the v3 string events ('cart.added', 'cart.stored', ...).
    | Set this to 'false' to dispatch only the typed events.
    |
    */

    'use_legacy_events' => true,

    /*
    |--------------------------------------------------------------------------
    | Cart TTL (time to live)
    |--------------------------------------------------------------------------
    |
    | Number of minutes before a stored cart expires. null = no expiration.
    | Applies only to the database storage driver.
    |
    */

    'ttl' => null,

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    */

    'guest_transfer' => [
        'enabled' => false,
        'instances' => ['default'],
    ],

    'middleware' => [

        'merge_on_login' => false,

        'auto_restore' => false,

    ],

    /*
    |--------------------------------------------------------------------------
    | Default number format
    |--------------------------------------------------------------------------
    |
    | This defaults will be used for the formatted numbers if you don't
    | set them in the method call.
    |
    */

    'format' => [

        'decimals' => 2,

        'decimal_point' => '.',

        'thousand_separator' => ',',

        'rounding_mode' => PHP_ROUND_HALF_UP,

    ],

    /*
    |--------------------------------------------------------------------------
    | Tax configuration
    |--------------------------------------------------------------------------
    |
    | Configure the default tax behavior. The 'default_rate' is applied
    | automatically when no explicit VAT is provided. Set 'tax_behavior'
    | to 'inclusive' (EU: price includes tax) or 'exclusive' (US: tax on top).
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Cart limits
    |--------------------------------------------------------------------------
    |
    | Configurable constraints on cart operations. Set to null to disable.
    |
    */

    'limits' => [

        'max_items' => null,

        'max_qty_per_item' => null,

        'max_total' => null,

        'min_total' => null,

    ],

    'tax' => [

        'default_rate' => 0.0,

        'tax_behavior' => 'inclusive',

        'fiscal_codes' => [
            'enabled' => false,
        ],

    ],
];
