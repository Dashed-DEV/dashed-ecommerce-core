<?php

namespace Qubiqx\QcommerceEcommerceCore\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductCharacteristic extends Model
{
    use HasTranslations;
    use SoftDeletes;
    use LogsActivity;

    protected static $logFillable = true;

    protected $fillable = [
        'value',
        'order',
        'hide_from_public',
    ];

    public $translatable = [
        'value',
    ];

    protected $table = 'qcommerce__product_characteristic';

    public function product()
    {
        return $this->belongsto(Product::class);
    }

    public function productCharacteristic()
    {
        return $this->belongsto(ProductCharacteristics::class);
    }
}
