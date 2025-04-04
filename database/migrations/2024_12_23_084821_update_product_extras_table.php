<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Dashed\DashedEcommerceCore\Models\ProductExtra;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('dashed__product_extras', function (Blueprint $table) {
            $table->integer('order')
                ->default(0);
            $table->boolean('global')
                ->default(false);
        });

        Schema::create('dashed__product_extra_product', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained('dashed__products')
                ->cascadeOnDelete();
            $table->foreignId('product_extra_id')
                ->constrained('dashed__product_extras')
                ->cascadeOnDelete();
        });

        foreach (ProductExtra::withTrashed()->get() as $extra) {
            \Illuminate\Support\Facades\DB::table('dashed__product_extra_product')->insert([
                'product_id' => $extra->product_id,
                'product_extra_id' => $extra->id,
            ]);
        }

        try {
            Schema::table('dashed__product_extras', function (Blueprint $table) {
                $table->dropForeign('qcommerce__product_extras_product_id_foreign');
            });
        } catch (\Exception $e) {
        }

        try {
            Schema::table('dashed__product_extras', function (Blueprint $table) {
                $table->dropForeign('dashed__product_extras_product_id_foreign');
            });
        } catch (\Exception $e) {
        }
        Schema::table('dashed__product_extras', function (Blueprint $table) {
            $table->dropColumn('product_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('product', function (Blueprint $table) {
            //
        });
    }
};
