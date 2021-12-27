<?php

namespace Qubiqx\QcommerceEcommerceCore\Models;

use Illuminate\Support\Str;
use Qubiqx\Qcommerce\Classes\Helper;
use Illuminate\Database\Eloquent\Model;
use Qubiqx\Qcommerce\Classes\ShoppingCart;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderPayment extends Model
{
    use LogsActivity;

    protected static $logFillable = true;

    protected $table = 'qcommerce__order_payments';

    protected $fillable = [
        'order_id',
        'psp',
        'psp_id',
        'payment_method',
        'payment_method_id',
        'psp_payment_method_id',
        'amount',
        'status',
        'payment_hash',
    ];

    protected $appends = [
        'payment_method_name',
        'amount_formatted',
    ];

    public static function boot()
    {
        parent::boot();

        static::creating(function ($orderPayment) {
            $orderPayment->hash = Str::random(32);
        });
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class)->withTrashed();
    }

    public function getPaymentMethodNameAttribute(): string
    {
        if ($this->paymentMethod) {
            return $this->paymentMethod->name;
        } else {
            return $this->payment_method;
        }
    }

    public function getPaymentMethodInstructionsAttribute(): string
    {
        if ($this->paymentMethod) {
            return $this->paymentMethod->payment_instructions;
        } else {
            foreach (ShoppingCart::getAllPaymentMethods() as $paymentMethod) {
                if ($paymentMethod['id'] == $this->psp_payment_method_id) {
                    return (string)$paymentMethod['payment_instructions'];
                }
            }

            return '';
        }
    }

    public function getAmountFormattedAttribute()
    {
        return Helper::formatPrice($this->amount);
    }

    public function changeStatus($newStatus = null, $sendMail = false): string
    {
        if (! $newStatus || $this->status == $newStatus) {
            return '';
        }

        $this->status = $newStatus;
        $this->save();

        if ($newStatus == 'cancelled') {
            return 'cancelled';
        } elseif ($newStatus == 'paid') {
            if ($this->order->orderPayments()->where('status', 'paid')->sum('amount') >= $this->order->total) {
                return 'paid';
            } else {
                return 'partially_paid';
            }
        }
    }
}