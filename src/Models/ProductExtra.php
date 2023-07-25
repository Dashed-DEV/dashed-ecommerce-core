<?php

namespace Qubiqx\QcommerceEcommerceCore\Models;

use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Qubiqx\QcommerceCore\Models\Concerns\HasCustomBlocks;

class ProductExtra extends Model
{
    use HasTranslations;
    use SoftDeletes;
    use LogsActivity;
    use HasCustomBlocks;

    protected static $logFillable = true;

    protected $fillable = [
        'product_id',
        'name',
        'type',
        'required',
    ];

    public $translatable = [
        'name',
    ];

    protected $table = 'qcommerce__product_extras';

    public static function boot()
    {
        parent::boot();

        static::deleting(function ($productExtra) {
            foreach ($productExtra->productExtraOptions as $productExtraOption) {
                $productExtraOption->delete();
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }

    public function product()
    {
        return $this->belongsto(Product::class);
    }

    public function productExtraOptions()
    {
        return $this->hasMany(ProductExtraOption::class);
    }
}
