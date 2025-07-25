<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('dashed__products', function (Blueprint $table) {
            $table->integer('add_to_cart_count')
                ->default(0);
            $table->integer('remove_from_cart_count')
                ->default(0);
        });

        Schema::table('dashed__product_groups', function (Blueprint $table) {
            $table->integer('add_to_cart_count')
                ->default(0);
            $table->integer('remove_from_cart_count')
                ->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
