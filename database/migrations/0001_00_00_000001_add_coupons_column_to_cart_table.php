<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCouponsColumnToCartTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table(config('cart.database.table', 'cart'), function (Blueprint $table): void {
            $table->json('coupons')->nullable()->after('content');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(config('cart.database.table', 'cart'), function (Blueprint $table): void {
            $table->dropColumn('coupons');
        });
    }
}
