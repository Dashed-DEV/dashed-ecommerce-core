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

        Schema::create('dashed__ecommerce_action_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_group_id')
                ->nullable()
                ->constrained('dashed__product_groups')
                ->nullOnDelete();
            $table->foreignId('product_id')
                ->nullable()
                ->constrained('dashed__products')
                ->nullOnDelete();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('order_id')
                ->nullable()
                ->constrained('dashed__orders')
                ->nullOnDelete();

            $table->string('action_type');
            $table->integer('quantity')
                ->default(1);


            $table->timestamps();
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
