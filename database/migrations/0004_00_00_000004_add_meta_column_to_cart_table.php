<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('cart.database.table', 'cart');

        if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'meta')) {
            Schema::table($table, function (Blueprint $table) {
                $table->json('meta')->nullable()->after('coupons');
            });
        }
    }

    public function down(): void
    {
        $table = config('cart.database.table', 'cart');

        if (Schema::hasTable($table) && Schema::hasColumn($table, 'meta')) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('meta');
            });
        }
    }
};
