<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('dashed__product_categories', function (Blueprint $table) {
            $table->dateTime('start_date')->nullable();
            $table->dateTime('end_date')->nullable();
        });

        Schema::table('dashed__product_categories', function (Blueprint $table) {
            $table->dropForeign('dashed__product_categories_parent_category_id_foreign');
            $table->renameColumn('parent_category_id', 'parent_id');
        });

        Schema::table('dashed__product_categories', function (Blueprint $table) {
            $table->foreignId('parent_id')
                ->change()
                ->nullable()
                ->constrained('dashed__product_categories')
                ->nullOnDelete();
        });

        Schema::table('dashed__products', function (Blueprint $table) {
            $table->dropForeign('dashed__products_parent_product_id_foreign');
            $table->renameColumn('parent_product_id', 'parent_id');
        });

        Schema::table('dashed__products', function (Blueprint $table) {
            $table->foreignId('parent_id')
                ->change()
                ->nullable()
                ->constrained('dashed__products')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
};
