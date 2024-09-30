<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach(\Dashed\DashedEcommerceCore\Models\Order::all() as $order){
            if(!$order->vat_percentages){
                $order->vat_percentages = [
                    '21' => $order->btw,
                ];
                $order->save();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
