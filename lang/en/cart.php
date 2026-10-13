<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tax Labels
    |--------------------------------------------------------------------------
    */

    'tax_included' => 'VAT Included',
    'tax_exempt' => 'Tax Exempt',
    'tax_name' => 'VAT',

    /*
    |--------------------------------------------------------------------------
    | Coupon Messages
    |--------------------------------------------------------------------------
    */

    'coupon_applied' => 'Coupon ":code" applied successfully.',
    'coupon_removed' => 'Coupon ":code" removed.',
    'coupon_already_applied' => 'Coupon ":code" is already applied.',
    'coupon_not_found' => 'Coupon ":code" was not found.',
    'coupon_expired' => 'Coupon ":code" has expired.',
    'coupon_min_amount' => 'Coupon ":code" requires a minimum cart total of :amount.',
    'coupon_invalid' => 'Coupon ":code" is not valid.',

    /*
    |--------------------------------------------------------------------------
    | Cart Messages
    |--------------------------------------------------------------------------
    */

    'cart_already_stored' => 'A cart with identifier ":identifier" was already stored.',
    'stored_cart_undecodable' => 'The stored cart with identifier ":identifier" could not be decoded.',
    'cart_expired' => 'The cart has expired.',
    'cart_empty' => 'The cart is empty.',

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    'invalid_id' => 'Please supply a valid identifier.',
    'invalid_name' => 'Please supply a valid name.',
    'invalid_qty' => 'Please supply a valid quantity. Provided: :qty',
    'invalid_price' => 'Please supply a valid price.',
    'invalid_row_id' => 'Please supply a valid row ID.',
    'unknown_model' => 'The model ":model" does not exist.',

    /*
    |--------------------------------------------------------------------------
    | Limits
    |--------------------------------------------------------------------------
    */

    'limit_exceeded' => 'Cart limit exceeded: :type (max: :limit).',
    'limit_max_items' => 'maximum items',
    'limit_max_qty' => 'maximum quantity per item',
    'limit_max_total' => 'maximum cart total',

    /*
    |--------------------------------------------------------------------------
    | Artisan Commands
    |--------------------------------------------------------------------------
    */

    'command_clear_confirm' => 'This will delete ALL stored cart records from the database. Continue?',
    'command_clear_instance' => 'This will delete all stored cart records for instance ":instance". Continue?',
    'command_clear_done' => 'Cleared :count cart record(s) from the ":table" table.',
    'command_clear_instance_done' => 'Cleared :count cart record(s) for instance ":instance".',
    'command_clear_cancelled' => 'Operation cancelled.',

    'command_prune_confirm' => 'This will delete :count cart(s) older than :hours hours. Continue?',
    'command_prune_done' => 'Deleted :count cart(s) older than :hours hours.',
    'command_prune_dry_run' => 'Found :count cart(s) older than :hours hours.',
    'command_prune_empty' => 'No carts to prune.',
    'command_prune_no_records' => 'No records deleted.',

    'command_export_done' => 'Exported to :path (:size)',
    'command_export_warning' => 'Exporting more than :limit carts. This may take a while.',
    'command_export_empty' => 'No stored carts found matching criteria.',
    'command_export_dir_not_found' => 'Directory does not exist: :dir',
    'command_export_invalid_date' => 'Invalid date format: :date. Use Y-m-d.',

    'command_migrate_done' => 'Migrated :migrated carts. Failed: :failed. Skipped: :skipped.',
    'command_migrate_error' => ':identifier: :error',
    'command_migrate_confirm' => 'Migrate cart data from :count session(s) to the database? Continue?',
    'command_migrate_same_driver' => 'Source and target drivers must be different.',
    'command_migrate_invalid_driver' => 'Valid drivers are: session, database.',
    'command_migrate_no_db_to_session' => 'Cannot migrate from database to session via CLI. Session writes require an active HTTP request. Use Cart::restore() within an authenticated request context instead.',
    'command_migrate_unsupported_session' => 'Session driver ":driver" is not supported for CLI migration. Only "database" and "file" session drivers can be read from the CLI.',
    'command_migrate_dry_run' => 'Found :count sessions to scan. No records written.',
    'command_migrate_empty' => 'No sessions found.',
    'command_migrate_session_dir_not_found' => 'Session files directory not found: :path',
    'command_migrate_failed_warning' => ':count carts failed to migrate (see above for details).',

    'command_inspect_not_found' => 'No cart found for identifier ":identifier" (instance: :instance).',
    'command_inspect_error' => 'Failed to deserialize cart content. Use --raw to view raw data.',

    'command_stats_empty' => 'No stored carts found.',

    'command_list_empty' => 'No stored carts found.',
    'command_list_footer' => 'Showing :shown of :total stored carts.',

];
