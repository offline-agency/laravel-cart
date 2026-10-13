<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $table = config()->string('cart.database.table', 'cart');

        if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'coupons')) {
            Schema::table($table, function (Blueprint $table): void {
                $table->json('coupons')->nullable()->after('content');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $table = config()->string('cart.database.table', 'cart');

        if (Schema::hasTable($table) && Schema::hasColumn($table, 'coupons')) {
            Schema::table($table, function (Blueprint $table): void {
                $table->dropColumn('coupons');
            });
        }
    }
};
