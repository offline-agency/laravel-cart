<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $cartsTable = config('cart.database.carts_table', 'carts');
        $table = config('cart.database.cart_items_table', 'cart_items');

        Schema::create($table, function (Blueprint $table) use ($cartsTable) {
            $table->id();
            $table->foreignId('cart_id')->constrained($cartsTable)->cascadeOnDelete();
            $table->string('row_id');
            $table->string('item_id');
            $table->string('name');
            $table->string('subtitle')->default('');
            $table->decimal('original_price', 12, 4)->default(0);
            $table->decimal('original_total_price', 12, 4)->default(0);
            $table->decimal('original_vat', 12, 4)->default(0);
            $table->decimal('price', 12, 4)->default(0);
            $table->decimal('total_price', 12, 4)->default(0);
            $table->decimal('vat', 12, 4)->default(0);
            $table->decimal('vat_rate', 8, 4)->default(0);
            $table->string('vat_fc_code')->default('');
            $table->string('product_fc_code')->default('');
            $table->string('url_img')->default('');
            $table->decimal('qty', 12, 4)->default(1);
            $table->json('options')->nullable();
            $table->json('applied_coupons')->nullable();
            $table->decimal('discount_value', 12, 4)->default(0);
            $table->string('associated_model')->nullable();
            $table->timestamps();

            $table->unique(['cart_id', 'row_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('cart.database.cart_items_table', 'cart_items'));
    }
};
