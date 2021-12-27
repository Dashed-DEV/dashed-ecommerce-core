<?php

namespace Qubiqx\QcommerceEcommerceCore\Models;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Qubiqx\QcommerceCore\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Qubiqx\QcommerceCore\Classes\Mails;
use Qubiqx\QcommerceCore\Classes\Sites;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Qubiqx\QcommerceCore\Models\Customsetting;
use Qubiqx\QcommerceEcommerceCore\Classes\Orders;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Qubiqx\QcommerceEcommerceCore\Classes\Countries;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Qubiqx\QcommerceEcommerceCore\Classes\ShoppingCart;
use Qubiqx\QcommerceEcommerceCore\Mail\OrderCancelledMail;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use Qubiqx\QcommerceEcommerceCore\Mail\ProductOnLowStockEmail;
use Qubiqx\QcommerceEcommerceCore\Mail\AdminOrderCancelledMail;
use Qubiqx\QcommerceEcommerceCore\Mail\AdminOrderConfirmationMail;
use Qubiqx\QcommerceEcommerceCore\Mail\OrderCancelledWithCreditMail;
use Qubiqx\QcommerceEcommerceCore\Mail\AdminPreOrderConfirmationMail;
use Qubiqx\QcommerceEcommerceCore\Mail\OrderFulfillmentStatusChangedMail;

class Order extends Model
{
    use SoftDeletes;
    use LogsActivity;

    protected static $logFillable = true;

    protected $table = 'qcommerce__orders';

    protected $fillable = [
        'hash',
        'ip',
        'first_name',
        'last_name',
        'email',
        'phone_number',
        'street',
        'house_nr',
        'zip_code',
        'city',
        'country',
        'marketing',
        'company_name',
        'btw_id',
        'note',
        'invoice_first_name',
        'invoice_last_name',
        'invoice_street',
        'invoice_house_nr',
        'invoice_zip_code',
        'invoice_city',
        'invoice_country',
        'invoice_id',
        'psp',
        'psp_id',
        'payment_method',
        'has_deposit',
        'total',
        'subtotal',
        'btw',
        'discount',
        'status',
        'invoice_send_to_customer',
        'ga_user_id',
        'ga_commerce_hit_send',
        'discount_code_id',
        'user_id',
        'shipping_costs',
        'shipping_method_id',
        'site_id',
        'locale',
        'fulfillment_status',
        'payment_costs',
        'payment_method_id',
        'contains_pre_orders',
        'date_of_birth',
        'initials',
        'gender',
        'order_origin',
        'credit_for_order_id',
        'retour_status',
    ];

    protected $appends = [
        'name',
        'invoiceName',
        'paymentMethod',
        'paidAmount',
        'openAmount',
    ];

    protected $with = [
        'orderProducts',
        'orderPayments',
    ];

    public static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            $order->ip = request()->ip();
            $order->hash = Str::random(32);
            $order->locale = App::getLocale();
            $order->initials = $order->first_name ? strtoupper($order->first_name[0]) . '.' : '';
            $order->site_id = Sites::getActive();
        });
    }

    public function getNameAttribute(): string
    {
        if ($this->first_name || $this->last_name) {
            if ($this->first_name && $this->last_name) {
                return "$this->first_name $this->last_name";
            } elseif ($this->first_name) {
                return $this->first_name;
            } else {
                return $this->last_name;
            }
        } else {
            return $this->email;
        }
    }

    public function getInvoiceNameAttribute(): string
    {
        if ($this->invoice_first_name || $this->invoice_last_name) {
            if ($this->invoice_first_name && $this->invoice_last_name) {
                return "$this->invoice_first_name $this->invoice_last_name";
            } elseif ($this->invoice_first_name) {
                return $this->invoice_first_name;
            } else {
                return $this->invoice_last_name;
            }
        } else {
            return $this->name;
        }
    }

    public function getPaymentMethodAttribute(): string
    {
        return $this->orderPayments()->first() ? $this->orderPayments()->first()->payment_method_name : 'Geen methode beschikbaar';
    }

    public function getPaymentMethodInstructionsAttribute(): string
    {
        return $this->orderPayments()->first() ? $this->orderPayments()->first()->paymentMethodInstructions : '';
    }

    public function getPaidAmountAttribute()
    {
        return $this->orderPayments()->where('status', 'paid')->sum('amount');
    }

    public function getOpenAmountAttribute()
    {
        return $this->getRawOriginal('total') - $this->paidAmount;
    }

    public function getStatusLabelsAttribute(): array
    {
        $labels = [];

        if ($this->contains_pre_orders) {
            $labels[] = [
                'status' => 'Bevat pre-orders',
                'color' => 'yellow',
            ];
        }

//        if ($this->keen_delivery_shipment_id) {
//            $labels[] = [
//                'status' => 'Doorgezet naar KeenDelivery',
//                'color' => 'green'
//            ];
//        }

//        if ($this->keen_delivery_shipment_id && $this->keen_delivery_label_printed) {
//            $labels[] = [
//                'status' => 'Label geprint',
//                'color' => 'green'
//            ];
//        } elseif ($this->keen_delivery_shipment_id && !$this->keen_delivery_label_printed) {
//            $labels[] = [
//                'status' => 'Label niet geprint',
//                'color' => 'red'
//            ];
//        }

//        if ($this->montaPortalOrder) {
//            if ($this->montaPortalOrder->pushed_to_montaportal == 0) {
//                $labels[] = [
//                    'status' => 'Order nog niet doorgezet naar Montaportal',
//                    'color' => 'yellow'
//                ];
//            } elseif ($this->montaPortalOrder->pushed_to_montaportal == 1) {
//                $labels[] = [
//                    'status' => 'Order doorgezet naar Montaportal',
//                    'color' => 'green'
//                ];
//            } elseif ($this->montaPortalOrder->pushed_to_montaportal == 2) {
//                $labels[] = [
//                    'status' => 'Order naar Montaportal ging fout',
//                    'color' => 'red'
//                ];
//            }
//        }

//        if ($this->exactonlineOrder) {
//            if ($this->exactonlineOrder->pushed == 0) {
//                $labels[] = [
//                    'status' => 'Order nog niet doorgezet naar Exactonline',
//                    'color' => 'yellow'
//                ];
//            } elseif ($this->exactonlineOrder->pushed == 1) {
//                $labels[] = [
//                    'status' => 'Order doorgezet naar Exactonline',
//                    'color' => 'green'
//                ];
//            } elseif ($this->exactonlineOrder->pushed == 2) {
//                $labels[] = [
//                    'status' => 'Order naar Exactonline ging fout',
//                    'color' => 'red'
//                ];
//            }
//        }

        return $labels;
    }

    public function orderProducts(): HasMany
    {
        return $this->hasMany(OrderProduct::class)->withTrashed();
    }

    public function orderProductsWithProduct(): HasMany
    {
        return $this->hasMany(OrderProduct::class)->whereNotNull('product_id')->withTrashed();
    }

    public function orderPayments(): HasMany
    {
        return $this->hasMany(OrderPayment::class);
    }

    public function discountCode(): BelongsTo
    {
        return $this->belongsTo(DiscountCode::class);
    }

    public function parentCreditOrder()
    {
        return $this->belongsTo(Order::class, 'credit_for_order_id');
    }

    public function shippingMethod()
    {
        return $this->belongsTo(ShippingMethod::class);
    }

//    public function channableOrderConnection()
//    {
//        return $this->belongsTo(ChannableOrderConnection::class);
//    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function logs()
    {
        return $this->hasMany(OrderLog::class);
    }

    public function creditOrders()
    {
        return $this->hasMany(self::class, 'credit_for_order_id');
    }

//    public function montaPortalOrder()
//    {
//        return $this->hasOne(MontaportalOrder::class);
//    }

//    public function exactonlineOrder()
//    {
//        return $this->hasOne(ExactonlineOrder::class);
//    }

//    public function eboekhoudenOrderConnection()
//    {
//        return $this->belongsTo(EboekhoudenOrderConnection::class);
//    }

    public function isPaidFor()
    {
        if ($this->status == 'paid' || $this->status == 'partially_paid' || $this->status == 'waiting_for_confirmation') {
            return true;
        } else {
            return false;
        }
    }

    public function scopeIsPaid($query)
    {
        return $query->whereIn('status', ['paid', 'waiting_for_confirmation', 'partially_paid']);
    }

    public function scopeIsReturn($query)
    {
        return $query->whereIn('status', ['return']);
    }

    public function scopeIsPaidOrReturn($query)
    {
        return $query->whereIn('status', ['paid', 'waiting_for_confirmation', 'partially_paid', 'return']);
    }

    public function scopeThisSite($query)
    {
        return $query->where('site_id', Sites::getActive());
    }

    public function scopeCalculatableForStats($query)
    {
        return $query->whereNotIn('invoice_id', ['PROFORMA', 'RETURN']);
    }

    public function scopeUnhandled($query)
    {
        return $query->where('fulfillment_status', 'unhandled')->isPaid();
    }

    public function scopeNotHandled($query)
    {
        return $query->where('fulfillment_status', '!=', 'handled')->isPaid();
    }

//    public function scopePushableToEfulfillmentShop($query)
//    {
//        return $query->where('pushable_to_efulfillment_shop', 1)->where('pushed_to_efulfillment_shop', 0)->isPaid()->thisSite();
//    }
//
//    public function scopePushedToEfulfillmentShop($query)
//    {
//        return $query->where('pushable_to_efulfillment_shop', 1)->where('pushed_to_efulfillment_shop', 1)->isPaid()->thisSite();
//    }
//
//    public function scopePushableToEboekhouden($query)
//    {
//        return $query->where('pushable_to_eboekhouden', 1)->where('pushed_to_eboekhouden', 0);
//    }

    public function scopeSearch($query)
    {
        if (request()->get('search')) {
            $search = strtolower(request()->get('search'));
            $query->where('hash', 'LIKE', "%$search%")
                ->orWhere('id', 'LIKE', "%$search%")
                ->orWhere('ip', 'LIKE', "%$search%")
                ->orWhere('first_name', 'LIKE', "%$search%")
                ->orWhere('last_name', 'LIKE', "%$search%")
                ->orWhere('email', 'LIKE', "%$search%")
                ->orWhere('street', 'LIKE', "%$search%")
                ->orWhere('house_nr', 'LIKE', "%$search%")
                ->orWhere('zip_code', 'LIKE', "%$search%")
                ->orWhere('city', 'LIKE', "%$search%")
                ->orWhere('country', 'LIKE', "%$search%")
                ->orWhere('company_name', 'LIKE', "%$search%")
                ->orWhere('btw_id', 'LIKE', "%$search%")
                ->orWhere('note', 'LIKE', "%$search%")
                ->orWhere('invoice_first_name', 'LIKE', "%$search%")
                ->orWhere('invoice_last_name', 'LIKE', "%$search%")
                ->orWhere('invoice_street', 'LIKE', "%$search%")
                ->orWhere('invoice_house_nr', 'LIKE', "%$search%")
                ->orWhere('invoice_zip_code', 'LIKE', "%$search%")
                ->orWhere('invoice_city', 'LIKE', "%$search%")
                ->orWhere('invoice_country', 'LIKE', "%$search%")
                ->orWhere('invoice_id', 'LIKE', "%$search%")
                ->orWhere('total', 'LIKE', "%$search%")
                ->orWhere('subtotal', 'LIKE', "%$search%")
                ->orWhere('btw', 'LIKE', "%$search%")
                ->orWhere('discount', 'LIKE', "%$search%")
                ->orWhere('status', 'LIKE', "%$search%")
                ->orWhere('site_id', 'LIKE', "%$search%");
        }
    }

    public function getCountryIsoCodeAttribute()
    {
        return Countries::getCountryIsoCode($this->country);
    }

    public function labelPrinted()
    {
//        if ($this->keen_delivery_label_printed) {
//            return true;
//        }

        return false;
    }

    public function generateInvoiceId()
    {
        if ($this->order_origin == 'own' && ($this->invoice_id == 'PROFORMA' || $this->invoice_id == 'RETURN')) {
            if (Customsetting::get('random_invoice_number')) {
                $invoiceNumber = '';
                foreach (str_split(Customsetting::get('invoice_id_replacement', config('qcommerce.currentSite'), '*****')) as $codeCharacter) {
                    if ($codeCharacter == '*') {
                        $codeCharacter = strtoupper(Str::random(1));
                    }
                    $invoiceNumber .= $codeCharacter;
                }

                $invoiceId = Customsetting::get('invoice_id_prefix') . $invoiceNumber . Customsetting::get('invoice_id_suffix');
                while (Order::where('invoice_id', $invoiceId)->count()) {
                    $invoiceNumber = '';
                    foreach (str_split(Customsetting::get('invoice_id_replacement', config('qcommerce.currentSite'), '*****')) as $codeCharacter) {
                        if ($codeCharacter == '*') {
                            $codeCharacter = strtoupper(Str::random(1));
                        }
                        $invoiceNumber .= $codeCharacter;
                    }

                    $invoiceId = Customsetting::get('invoice_id_prefix') . $invoiceNumber . Customsetting::get('invoice_id_suffix');
                }
            } else {
                $invoiceNumber = Customsetting::get('current_invoice_number', config('qcommerce.currentSite'), 1000) + 1;
                $invoiceId = Customsetting::get('invoice_id_prefix') . $invoiceNumber . Customsetting::get('invoice_id_suffix');
                Customsetting::set('current_invoice_number', $invoiceNumber);
            }
            $this->invoice_id = $invoiceId;
            $this->save();
        }
    }

    public function createInvoice()
    {
        if ($this->order_origin == 'own') {
            if ($this->parentCreditOrder) {
                $this->createCreditInvoice();
            } elseif ($this->status == 'paid' || $this->status == 'waiting_for_confirmation' || $this->status == 'partially_paid') {
                $this->createNormalInvoice();
            }
        }
    }

    public function createNormalInvoice()
    {
        if ($this->order_origin == 'own') {
            $this->generateInvoiceId();
            $order = Order::find($this->id);
            if (! Storage::exists('/invoices/invoice-' . $order->invoice_id . '-' . $order->hash . '.pdf')) {
                $view = View::make('qcommerce::frontend.invoices.pdf', compact('order'));
                $contents = $view->render();
                $pdf = App::make('dompdf.wrapper');
                $pdf->loadHTML($contents);
                $output = $pdf->output();

                $invoicePath = '/invoices/invoice-' . $order->invoice_id . '-' . $order->hash . '.pdf';
                Storage::put($invoicePath, $output);
            }

            if (! $this->invoice_send_to_customer) {
                Orders::sendNotification($this);

                if (env('APP_ENV') == 'local') {
                    try {
                        if ($this->contains_pre_orders) {
                            Mail::to('robin@qubiqx.com')->send(new AdminPreOrderConfirmationMail($this));
                        } else {
                            Mail::to('robin@qubiqx.com')->send(new AdminOrderConfirmationMail($this));
                        }
                    } catch (\Exception $e) {
                    }
                } else {
                    try {
                        foreach (Mails::getAdminNotificationEmails() as $notificationInvoiceEmail) {
                            if ($this->contains_pre_orders) {
                                Mail::to($notificationInvoiceEmail)->send(new AdminPreOrderConfirmationMail($this));
                            } else {
                                Mail::to($notificationInvoiceEmail)->send(new AdminOrderConfirmationMail($this));
                            }
                        }
                    } catch (\Exception $e) {
                    }
                }
                $this->invoice_send_to_customer = 1;
                $this->save();
            }
        }
    }

    public function createPackingSlip()
    {
        if ($this->status == 'paid' || $this->status == 'waiting_for_confirmation' || $this->status == 'partially_paid' || $this->parentCreditOrder) {
            $order = Order::find($this->id);
            if (! Storage::exists('/packing-slips/packing-slip-' . ($order->invoice_id ?: $order->id) . '-' . $order->hash . '.pdf')) {
                $view = View::make('qcommerce::frontend.packing-slips.pdf', compact('order'));
                $contents = $view->render();
                $pdf = App::make('dompdf.wrapper');
                $pdf->loadHTML($contents);
                $output = $pdf->output();

                $invoicePath = '/packing-slips/packing-slip-' . ($order->invoice_id ? $order->invoice_id : $order->id) . '-' . $order->hash . '.pdf';
                Storage::put($invoicePath, $output);
            }
        }
    }

    public function createCreditInvoice()
    {
        if ($this->order_origin == 'own' && ($this->status == 'paid' || $this->status == 'waiting_for_confirmation' || $this->status == 'partially_paid' || $this->parentCreditOrder)) {
            $this->generateInvoiceId();
            $order = $this;
            if (! Storage::exists('/invoices/invoice-' . $order->invoice_id . '-' . $order->hash . '.pdf')) {
                $view = View::make('qcommerce::frontend.credit-invoices.pdf', compact('order'));
                $contents = $view->render();
                $pdf = App::make('dompdf.wrapper');
                $pdf->loadHTML($contents);
                $output = $pdf->output();

                $invoicePath = '/invoices/invoice-' . $order->invoice_id . '-' . $order->hash . '.pdf';
                Storage::put($invoicePath, $output);
            }
        }
    }

    public function deductStock()
    {
        foreach ($this->orderProducts as $orderProduct) {
            if ($orderProduct->product) {
                if ($orderProduct->product->use_stock) {
                    $orderProduct->product->stock = $orderProduct->product->stock - $orderProduct->quantity;
                }
                $orderProduct->product->purchases = $orderProduct->product->purchases + $orderProduct->quantity;
                $orderProduct->product->save();
            }
        }

        foreach (Product::whereIn('id', $this->orderProducts->pluck('product_id'))->get() as $product) {
            if ($product->low_stock_notification && $product->use_stock && $product->stock() < $product->low_stock_notification_limit) {
                if (env('APP_ENV') == 'local') {
                    try {
                        Mail::to('robin@qubiqx.com')->send(new ProductOnLowStockEmail($product));
                    } catch (\Exception $e) {
                    }
                } else {
                    try {
                        foreach (Mails::getAdminNotificationEmails() as $notificationInvoiceEmail) {
                            Mail::to($notificationInvoiceEmail)->send(new ProductOnLowStockEmail($product));
                        }
                    } catch (\Exception $e) {
                    }
                }
            }
        }
    }

    public function deductDiscount()
    {
        if ($this->discountCode) {
            if ($this->discountCode->use_stock) {
                $this->discountCode->stock = $this->discountCode->stock - 1;
            }
            $this->discountCode->stock_used = $this->discountCode->stock_used + 1;
            $this->discountCode->save();
        }
    }

    public function refillStock($refillPurchases = true)
    {
        foreach ($this->orderProducts as $orderProduct) {
            if ($orderProduct->product) {
                if ($orderProduct->product->use_stock) {
                    if ($orderProduct->quantity < 0) {
                        $orderProduct->product->stock = $orderProduct->product->stock - $orderProduct->quantity;
                    } else {
                        $orderProduct->product->stock = $orderProduct->product->stock + $orderProduct->quantity;
                    }
                }
                if ($refillPurchases) {
                    if ($orderProduct->quantity < 0) {
                        $orderProduct->product->purchases = $orderProduct->product->purchases + $orderProduct->quantity;
                    } else {
                        $orderProduct->product->purchases = $orderProduct->product->purchases - $orderProduct->quantity;
                    }
                }
                $orderProduct->product->save();
            }
        }
    }

    public function refillDiscount()
    {
        if ($this->discountCode) {
            if ($this->discountCode->use_stock) {
                $this->discountCode->stock = $this->discountCode->stock + 1;
            }
            $this->discountCode->stock_used = $this->discountCode->stock_used - 1;
            $this->discountCode->save();
        }
    }

    public function changeStatus($newStatus = null, $sendMail = false)
    {
        if (! $newStatus || $this->status == $newStatus) {
            return;
        }

        if ($newStatus == 'paid') {
            $this->markAsPaid();
        } elseif ($newStatus == 'partially_paid') {
            $this->markAsPartiallyPaid();
        } elseif ($newStatus == 'cancelled') {
            $this->markAsCancelled($sendMail);
        } elseif ($newStatus == 'waiting_for_confirmation') {
            $this->markAsWaitingForConfirmation();
        }
    }

    public function changeFulfillmentStatus($newStatus)
    {
        if ($this->fulfillment_status == $newStatus) {
            return;
        }

        $this->fulfillment_status = $newStatus;
        $this->save();
        if ($this->isPaidFor()) {
            foreach (Orders::getFulfillmentStatusses() as $key => $fulfillmentStatus) {
                if ($this->fulfillment_status == $key && Customsetting::get("fulfillment_status_{$key}_enabled", null, false, $this->locale)) {
                    if (env('APP_ENV') == 'local') {
                        try {
                            Mail::to('robin@qubiqx.com')->send(new OrderFulfillmentStatusChangedMail(Customsetting::get("fulfillment_status_{$key}_email_subject", null, null, $this->locale), Customsetting::get("fulfillment_status_{$key}_email_content", null, null, $this->locale)));
                            $orderLog = new OrderLog();
                            $orderLog->order_id = $this->id;
                            $orderLog->user_id = Auth::check() ? Auth::user()->id : null;
                            $orderLog->tag = "order.fulfillment-status-update-to-{$key}.mail.send";
                            $orderLog->save();
                        } catch (\Exception $e) {
                            $orderLog = new OrderLog();
                            $orderLog->order_id = $this->id;
                            $orderLog->user_id = Auth::check() ? Auth::user()->id : null;
                            $orderLog->tag = "order.fulfillment-status-update-to-{$key}.mail.not-send";
                            $orderLog->save();
                        }
                    } else {
                        try {
                            Mail::to($this->email)->send(new OrderFulfillmentStatusChangedMail(Customsetting::get("fulfillment_status_{$key}_email_subject", null, null, $this->locale), Customsetting::get("fulfillment_status_{$key}_email_content", null, null, $this->locale)));
                            $orderLog = new OrderLog();
                            $orderLog->order_id = $this->id;
                            $orderLog->user_id = Auth::check() ? Auth::user()->id : null;
                            $orderLog->tag = "order.fulfillment-status-update-to-{$key}.mail.send";
                            $orderLog->save();
                        } catch (\Exception $e) {
                            $orderLog = new OrderLog();
                            $orderLog->order_id = $this->id;
                            $orderLog->user_id = Auth::check() ? Auth::user()->id : null;
                            $orderLog->tag = "order.fulfillment-status-update-to-{$key}.mail.not-send";
                            $orderLog->save();
                        }
                    }
                }
            }
        }
    }

    public function markAsPushableToEfulfillment()
    {
        //Todo: emit event and catch in other packages
//        if (EfulfillmentShop::connected(Sites::getActive())) {
//            $this->pushable_to_efulfillment_shop = 1;
//            $this->save();
//        }

        //Todo: emit event and catch in other packages
//        if (Montaportal::connected(Sites::getActive())) {
//            $this->montaPortalOrder()->create([]);
//        }
    }

    public function markAsPushableToAccountancy()
    {
        //Todo: emit event and catch in other packages
//        if (Eboekhouden::connected(Sites::getActive())) {
//            $this->pushable_to_eboekhouden = 1;
//            $this->save();
//        }

        //Todo: emit event and catch in other packages
        //Safety because exactonline disconnects often
//        if (Customsetting::get('exactonline_client_id', Sites::getActive())) {
//            $this->exactonlineOrder()->create([]);
//        }
    }

    public function markAsPaid()
    {
        if ($this->status == 'waiting_for_confirmation' || $this->status == 'partially_paid') {
            $this->status = 'paid';
            $this->save();

            $orderLog = new OrderLog();
            $orderLog->order_id = $this->id;
            $orderLog->user_id = Auth::check() ? Auth::user()->id : null;
            $orderLog->tag = 'order.marked-as-paid';
            $orderLog->save();
        } else {
            $this->status = 'paid';
            $this->save();

            if (Auth::user() && Auth::user()->id != $this->user_id) {
                $orderLog = new OrderLog();
                $orderLog->order_id = $this->id;
                $orderLog->user_id = Auth::check() ? Auth::user()->id : null;
                $orderLog->tag = 'order.marked-as-paid';
                $orderLog->save();
            } else {
                $orderLog = new OrderLog();
                $orderLog->order_id = $this->id;
                $orderLog->user_id = Auth::check() ? Auth::user()->id : null;
                $orderLog->tag = 'order.paid';
                $orderLog->save();
            }

            $this->generateInvoiceId();
            $this->createInvoice();

            $this->deductStock();
            $this->deductDiscount();
            $this->markAsPushableToEfulfillment();
            $this->markAsPushableToAccountancy();
            $this->activateReviewEmailsToBeSend();

            ShoppingCart::emptyMyCart();

            $this->sendGAEcommerceHit();
        }
    }

    public function markAsPartiallyPaid()
    {
        if ($this->status == 'partially_paid') {
            return;
        }

        $this->status = 'partially_paid';
        $this->save();

        $orderLog = new OrderLog();
        $orderLog->order_id = $this->id;
        $orderLog->user_id = Auth::check() ? Auth::user()->id : null;
        $orderLog->tag = 'order.partially_paid';
        $orderLog->save();

        $this->generateInvoiceId();
        $this->createInvoice();

        $this->deductStock();
        $this->deductDiscount();
        $this->markAsPushableToEfulfillment();
        $this->markAsPushableToAccountancy();
        $this->activateReviewEmailsToBeSend();

        ShoppingCart::emptyMyCart();
        session(['discountCode' => '']);

        $this->sendGAEcommerceHit();
    }

    public function markAsWaitingForConfirmation()
    {
        $this->status = 'waiting_for_confirmation';
        $this->save();

        $orderLog = new OrderLog();
        $orderLog->order_id = $this->id;
        $orderLog->user_id = Auth::check() ? Auth::user()->id : null;
        $orderLog->tag = 'order.waiting_for_confirmation';
        $orderLog->save();

        $this->generateInvoiceId();
        $this->createInvoice();

        $this->deductStock();
        $this->deductDiscount();
        $this->markAsPushableToEfulfillment();
        $this->markAsPushableToAccountancy();
        $this->activateReviewEmailsToBeSend();

        ShoppingCart::emptyMyCart();
        session(['discountCode' => '']);

        $this->sendGAEcommerceHit();
    }

    public function markAsCancelled($sendMail = false)
    {
        if ($this->status == 'paid') {
            $this->refillStock();
            $this->refillDiscount();
        }

        $this->status = 'cancelled';
        $this->changeFulfillmentStatus('handled');
        $this->save();

        if (app()->runningInConsole()) {
            $orderLog = new OrderLog();
            $orderLog->order_id = $this->id;
            $orderLog->user_id = null;
            $orderLog->tag = 'order.system.cancelled';
            $orderLog->save();
        } else {
            $orderLog = new OrderLog();
            $orderLog->order_id = $this->id;
            $orderLog->user_id = Auth::check() ? Auth::user()->id : null;
            $orderLog->tag = 'order.cancelled';
            $orderLog->save();
        }

        if ($sendMail) {
            if (app()->runningInConsole()) {
                try {
                    Mail::to($this->email)->send(new OrderCancelledMail($this));
                    $orderLog = new OrderLog();
                    $orderLog->order_id = $this->id;
                    $orderLog->user_id = null;
                    $orderLog->tag = 'order.system.cancelled.mail.send';
                    $orderLog->save();
                } catch (\Exception $e) {
                    $orderLog = new OrderLog();
                    $orderLog->order_id = $this->id;
                    $orderLog->user_id = null;
                    $orderLog->tag = 'order.system.cancelled.mail.send.failed';
                    $orderLog->note = 'Error: ' . $e->getMessage();
                    $orderLog->save();
                }
            } else {
                try {
                    Mail::to($this->email)->send(new OrderCancelledMail($this));
                    $orderLog = new OrderLog();
                    $orderLog->order_id = $this->id;
                    $orderLog->user_id = Auth::check() ? Auth::user()->id : null;
                    $orderLog->tag = 'order.cancelled.mail.send';
                    $orderLog->save();
                } catch (\Exception $e) {
                    $orderLog = new OrderLog();
                    $orderLog->order_id = $this->id;
                    $orderLog->user_id = Auth::check() ? Auth::user()->id : null;
                    $orderLog->tag = 'order.cancelled.mail.send.failed';
                    $orderLog->note = 'Error: ' . $e->getMessage();
                    $orderLog->save();
                }
            }
        }
    }

    public function markAsCancelledWithCredit($sendCustomerEmail, $createCreditInvoice, $productsMustBeReturned, $restock, $refundDiscountCosts, $extraOrderLineName, $extraOrderLinePrice, $chosenOrderProducts, $fulfillmentStatus)
    {
        $newOrder = $this->replicate();
        $newOrder->hash = Str::random(32);
        $newOrder->invoice_id = 'RETURN';
        $newOrder->total = 0;
        $newOrder->subtotal = 0;
        $newOrder->btw = 0;
        $newOrder->discount = 0;
        $newOrder->status = 'return';
        $newOrder->invoice_send_to_customer = 0;
        $newOrder->ga_user_id = null;
        $newOrder->contains_pre_orders = 0;
        $newOrder->fulfillment_status = 'waiting_for_return';
        $newOrder->credit_for_order_id = $this->id;
        if ($productsMustBeReturned) {
            $newOrder->retour_status = 'waiting_for_return';
        } else {
            $newOrder->retour_status = 'handled';
        }
        $newOrder->save();

        if (app()->runningInConsole()) {
            $orderLog = new OrderLog();
            $orderLog->order_id = $newOrder->id;
            $orderLog->user_id = null;
            $orderLog->tag = 'order.system.cancelled';
            $orderLog->save();
        } else {
            $orderLog = new OrderLog();
            $orderLog->order_id = $newOrder->id;
            $orderLog->user_id = Auth::check() ? Auth::user()->id : null;
            $orderLog->tag = 'order.cancelled';
            $orderLog->save();
        }

        $calculateInclusiveTax = Customsetting::get('taxes_prices_include_taxes');

        $discountToGet = $this->discount;
        $discountToSave = 0;

        $vatPercentages = 0;
        $vatPercentageCount = 0;

        foreach ($chosenOrderProducts as $chosenOrderProduct) {
            if ($chosenOrderProduct['quantity'] > 0) {
                $orderProduct = new OrderProduct();
                $orderProduct->quantity = 0 - $chosenOrderProduct['quantity'];
                $orderProduct->product_id = $chosenOrderProduct['product_id'];
                $orderProduct->name = $chosenOrderProduct['name'];
                $orderProduct->price = 0 - (($chosenOrderProduct['price'] / $chosenOrderProduct['original_quantity']) * $chosenOrderProduct['quantity']);
                $orderProduct->discount = 0 - $chosenOrderProduct['discount'];
                $orderProduct->product_extras = $chosenOrderProduct['product_extras'];
                $orderProduct->sku = $chosenOrderProduct['sku'];
                $orderProduct->vat_rate = $chosenOrderProduct['vat_rate'];
                $orderProduct->order_id = $newOrder->id;
                $orderProduct->save();

                $discountToGet -= $chosenOrderProduct['discount'];
                $discountToSave += $chosenOrderProduct['discount'];

                $vatPercentages += ($orderProduct->vat_rate * $chosenOrderProduct['quantity']);
                $vatPercentageCount += $chosenOrderProduct['quantity'];

                $price = $orderProduct->price;

                $newOrder->total += $price;

                if ($calculateInclusiveTax) {
                    $taxPrice = $price / (100 + $orderProduct->vat_rate) * $orderProduct->vat_rate;
                } else {
                    $taxPrice = $price / 100 * $orderProduct->vat_rate;
                }

                $newOrder->btw += $taxPrice;
            }
        }

        if ($extraOrderLineName || $extraOrderLinePrice != 0) {
            $orderProduct = new OrderProduct();
            $orderProduct->quantity = 1;
            $orderProduct->product_id = null;
            $orderProduct->name = $extraOrderLineName ?: 'Extra';
            $orderProduct->price = 0 - $extraOrderLinePrice;
            $orderProduct->discount = 0;
            $orderProduct->order_id = $newOrder->id;
            $orderProduct->sku = Str::slug($orderProduct->name);
            $orderProduct->vat_rate = 21;
            $orderProduct->save();

            $price = $orderProduct->price;

            $newOrder->total += $price;

            if ($calculateInclusiveTax) {
                $taxPrice = $price / 121 * 21;
            } else {
                $taxPrice = $price / 100 * 21;
            }

            $newOrder->btw += $taxPrice;
        }

        $newOrder->subtotal = $newOrder->total;

        if ($refundDiscountCosts && $discountToGet > 0.00) {
            if ($calculateInclusiveTax) {
                $newOrder->btw += ($discountToGet / (100 + ($vatPercentages / $vatPercentageCount)) * ($vatPercentages / $vatPercentageCount));
            } else {
                $newOrder->btw += ($discountToGet / 100 * ($vatPercentages / $vatPercentageCount));
            }

            $newOrder->total += $discountToGet;
            $discountToSave += $discountToGet;
        }
        $newOrder->discount = $discountToSave;

        $newOrder->save();
        $newOrder->refresh();

        if ($createCreditInvoice) {
            $newOrder->createInvoice();
        }

        if ($sendCustomerEmail) {
            if (app()->runningInConsole()) {
                try {
                    if (env('APP_ENV') == 'local') {
                        if ($createCreditInvoice) {
                            Mail::to('robin@qubiqx.com')->send(new OrderCancelledWithCreditMail($newOrder));
                        } else {
                            Mail::to('robin@qubiqx.com')->send(new OrderCancelledMail($newOrder));
                        }
                    } else {
                        if ($createCreditInvoice) {
                            Mail::to($this->email)->send(new OrderCancelledWithCreditMail($newOrder));
                        } else {
                            Mail::to($this->email)->send(new OrderCancelledMail($newOrder));
                        }
                    }
                    $orderLog = new OrderLog();
                    $orderLog->order_id = $newOrder->id;
                    $orderLog->user_id = null;
                    $orderLog->tag = 'order.system.cancelled.mail.send';
                    $orderLog->save();
                } catch (\Exception $e) {
                    $orderLog = new OrderLog();
                    $orderLog->order_id = $this->id;
                    $orderLog->user_id = null;
                    $orderLog->tag = 'order.system.cancelled.mail.send.failed';
                    $orderLog->note = 'Error: ' . $e->getMessage();
                    $orderLog->save();
                }

                if ($createCreditInvoice) {
                    if (env('APP_ENV') == 'local') {
                        try {
                            Mail::to('robin@qubiqx.com')->send(new AdminOrderCancelledMail($newOrder));
                        } catch (\Exception $e) {
                        }
                    } else {
                        try {
                            foreach (Mails::getAdminNotificationEmails() as $notificationInvoiceEmail) {
                                Mail::to($notificationInvoiceEmail)->send(new AdminOrderCancelledMail($newOrder));
                            }
                        } catch (\Exception $e) {
                        }
                    }
                }
            } else {
                try {
                    if (env('APP_ENV') == 'local') {
                        if ($createCreditInvoice) {
                            Mail::to('robin@qubiqx.com')->send(new OrderCancelledWithCreditMail($newOrder));
                        } else {
                            Mail::to('robin@qubiqx.com')->send(new OrderCancelledMail($newOrder));
                        }
                    } else {
                        if ($createCreditInvoice) {
                            Mail::to($this->email)->send(new OrderCancelledWithCreditMail($newOrder));
                        } else {
                            Mail::to($this->email)->send(new OrderCancelledMail($newOrder));
                        }
                    }
                    $orderLog = new OrderLog();
                    $orderLog->order_id = $newOrder->id;
                    $orderLog->user_id = Auth::check() ? Auth::user()->id : null;
                    $orderLog->tag = 'order.cancelled.mail.send';
                    $orderLog->save();
                } catch (\Exception $e) {
                    $orderLog = new OrderLog();
                    $orderLog->order_id = $this->id;
                    $orderLog->user_id = Auth::check() ? Auth::user()->id : null;
                    $orderLog->tag = 'order.cancelled.mail.send.failed';
                    $orderLog->note = 'Error: ' . $e->getMessage();
                    $orderLog->save();
                }

                if ($createCreditInvoice) {
                    if (env('APP_ENV') == 'local') {
                        try {
                            Mail::to('robin@qubiqx.com')->send(new AdminOrderCancelledMail($newOrder));
                        } catch (\Exception $e) {
                        }
                    } else {
                        try {
                            foreach (Mails::getAdminNotificationEmails() as $notificationInvoiceEmail) {
                                Mail::to($notificationInvoiceEmail)->send(new AdminOrderCancelledMail($newOrder));
                            }
                        } catch (\Exception $e) {
                        }
                    }
                }
            }
            $newOrder->invoice_send_to_customer = 1;
            $newOrder->save();
        }

        if ($restock) {
            $newOrder->refillStock(false);
        }

        $this->markAsPushableToAccountancy();
        $this->changeFulfillmentStatus($fulfillmentStatus);

        return $newOrder;
    }

    public function sendGAEcommerceHit()
    {
        if ($this->ga_user_id && ! $this->ga_commerce_hit_send && env('APP_ENV') != 'local' && Customsetting::get('google_analytics_id')) {
            if (! Customsetting::get('google_tagmanager_id')) {
                $data = [
                    'v' => 1,
                    'tid' => Customsetting::get('google_analytics_id'),
                    'cid' => $this->ga_user_id,
                    't' => 'event',
                ];

                $data['ti'] = $this->invoice_id;
                $data['ta'] = url('/');
                $data['tr'] = $this->total;
                $data['tt'] = $this->btw;
                $data['cu'] = 'EUR';
                $data['pa'] = 'purchase';
                $url = 'https://www.google-analytics.com/collect';
                $content = http_build_query($data);
                $content = utf8_encode($content);
                $user_agent = urlencode($_SERVER['HTTP_USER_AGENT']);

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-type: application/x-www-form-urlencoded']);
                curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
                curl_exec($ch);
                curl_close($ch);
            }

            $this->ga_commerce_hit_send = 1;
            $this->save();
        }
    }

    public function activateReviewEmailsToBeSend()
    {
        //Todo: emit and catch in own package
//        Webwinkelkeur::sendReviewEmail($this);
    }

    public function downloadInvoiceUrl()
    {
        $this->createInvoice();

        if (file_exists(storage_path('app/invoices/invoice-' . $this->invoice_id . '-' . $this->hash . '.pdf'))) {
            return route('qcommerce.frontend.download-invoice', ['orderHash' => $this->hash]);
        } else {
            return '';
        }
    }

    public function downloadPackingslipUrl()
    {
        $this->createPackingSlip();

        if (file_exists(storage_path('app/packing-slips/packing-slip-' . ($this->invoice_id ?: $this->id) . '-' . $this->hash . '.pdf'))) {
            return route('qcommerce.frontend.download-packing-slip', ['orderHash' => $this->hash]);
        } else {
            return '';
        }
    }

    public function getUrl()
    {
        return LaravelLocalization::localizeUrl(route('qcommerce.frontend.checkout.complete') . '?orderId=' . $this->hash . '&paymentId=' . ($this->orderPayments ? $this->orderPayments()->latest()->first()->hash : ''));
    }

    public function fulfillmentStatus()
    {
        if (! $this->credit_for_order_id) {
            if ($this->fulfillment_status == 'unhandled') {
                return [
                    'status' => Orders::getFulfillmentStatusses()[$this->fulfillment_status]['name'] ?? '',
                    'color' => 'red',
                ];
            } else {
                return [
                    'status' => Orders::getFulfillmentStatusses()[$this->fulfillment_status]['name'] ?? '',
                    'color' => 'green',
                ];
            }
        } else {
            if ($this->retour_status == 'unhandled') {
                return [
                    'status' => $this->retourStatus(),
                    'color' => 'red',
                ];
            } else {
                return [
                    'status' => $this->retourStatus(),
                    'color' => 'green',
                ];
            }
        }
//        return Orders::getFulfillmentStatusses()[$this->fulfillment_status]['name'] ?? '';
    }

    public function orderStatus()
    {
        if ($this->status == 'pending') {
            return [
                'status' => 'Lopende aankoop',
                'color' => 'blue',
            ];
        } elseif ($this->status == 'cancelled') {
            return [
                'status' => 'Geannuleerd',
                'color' => 'red',
            ];
        } elseif ($this->status == 'waiting_for_confirmation') {
            return [
                'status' => 'Wachten op bevestiging betaling',
                'color' => 'purple',
            ];
        } elseif ($this->status == 'return') {
            return [
                'status' => 'Retour',
                'color' => 'yellow',
            ];
        } elseif ($this->status == 'partially_paid') {
            return [
                'status' => 'Gedeeltelijk betaald',
                'color' => 'yellow',
            ];
        } else {
            return [
                'status' => 'Betaald',
                'color' => 'green',
            ];
        }
    }

    public function retourStatus()
    {
        if ($this->retour_status == 'unhandled') {
            return 'Niet afgehandeld';
        } elseif ($this->retour_status == 'handled') {
            return 'Afgehandeld';
        } elseif ($this->retour_status == 'received') {
            return 'Ontvangen';
        } elseif ($this->retour_status == 'shipped') {
            return 'Verzonden';
        } elseif ($this->retour_status == 'waiting_for_return') {
            return 'Wachten op retour';
        }
    }
}