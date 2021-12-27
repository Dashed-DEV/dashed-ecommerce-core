<?php

namespace Qubiqx\QcommerceEcommerceCore\Models;

use Qubiqx\Qcommerce\Classes\Helper;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderProduct extends Model
{
    use SoftDeletes;
    use LogsActivity;

    protected static $logFillable = true;

    protected $table = 'qcommerce__order_products';

    protected $fillable = [
        'quantity',
        'is_pre_order',
        'order_id',
        'name',
        'product_id',
        'price',
        'discount',
        'btw',
        'vat_rate',
        'product_extras',
    ];

    public static function boot()
    {
        parent::boot();

        static::creating(function ($orderProduct) {
            if ($orderProduct->product) {
                $orderProduct->vat_rate = $orderProduct->product->vat_rate;
                $orderProduct->btw = $orderProduct->price / (100 + ($orderProduct->vat_rate ?? 21)) * ($orderProduct->vat_rate ?? 21);
            } else {
                if (! $orderProduct->vat_rate) {
                    $orderProduct->vat_rate = round($orderProduct->btw / ($orderProduct->price - $orderProduct->btw), 2) * 100;
                }
                if ($orderProduct->btw == 0.00 && $orderProduct->vat_rate > 0.00) {
                    $orderProduct->btw = $orderProduct->price / (100 + ($orderProduct->vat_rate ?? 21)) * ($orderProduct->vat_rate ?? 21);
                }
            }
        });
    }

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function getPriceWithoutDiscountAttribute()
    {
        return $this->price + $this->discount;
    }

    public function getVatWithoutDiscountAttribute()
    {
        return Helper::calculateTax($this->price + $this->discount, $this->vat_rate);
    }
}