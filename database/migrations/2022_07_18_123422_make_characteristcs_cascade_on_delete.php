<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('qcommerce__product_characteristic', function (Blueprint $table) {
            $table->dropForeign('qcommerce__product_characteristic_product_id_foreign');
            $table->dropForeign('product_characteristic_id_foreign');

            $table->foreign('product_characteristic_id', 'product_characteristic_id_foreign')
                ->references('id')
                ->on('qcommerce__product_characteristics')
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->change()
                ->constrained('qcommerce__products')
                ->cascadeOnDelete();
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
