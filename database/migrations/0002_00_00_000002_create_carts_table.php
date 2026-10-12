<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('cart.database.carts_table', 'carts');
        $fkType = config('cart.users.foreign_key_type', 'unsignedBigInteger');
        $fkColumn = config('cart.users.foreign_key', 'user_id');

        Schema::create($table, function (Blueprint $table) use ($fkType, $fkColumn) {
            $table->id();
            $table->{$fkType}($fkColumn)->index();
            $table->string('instance')->index();
            $table->json('coupons')->nullable();
            $table->json('options')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique([$fkColumn, 'instance']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('cart.database.carts_table', 'carts'));
    }
};
