<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config()->string('cart.database.carts_table', 'carts');
        $fkType = config()->string('cart.users.foreign_key_type', 'unsignedBigInteger');
        $fkColumn = config()->string('cart.users.foreign_key', 'user_id');

        Schema::create($table, function (Blueprint $table) use ($fkType, $fkColumn) {
            $table->id();
            // Authenticated owner. Guests are identified by session_id instead,
            // so a session id is never written to the user foreign key column.
            $ownerColumn = $table->{$fkType}($fkColumn);

            if (! $ownerColumn instanceof ColumnDefinition) {
                throw new InvalidArgumentException("[cart.users.foreign_key_type] must name a column type method of the schema Blueprint, [{$fkType}] given.");
            }

            $ownerColumn->nullable()->index();
            $table->string('session_id')->nullable()->index();
            $table->string('instance')->index();
            $table->json('coupons')->nullable();
            $table->json('options')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            // One cart per owner and instance (NULLs are distinct, so each
            // index only constrains its own kind of owner).
            $table->unique([$fkColumn, 'instance']);
            $table->unique(['session_id', 'instance']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config()->string('cart.database.carts_table', 'carts'));
    }
};
