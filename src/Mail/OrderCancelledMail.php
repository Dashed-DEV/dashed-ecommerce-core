<?php

namespace Dashed\DashedEcommerceCore\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedTranslations\Models\Translation;

class OrderCancelledMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $mail = $this->view('dashed-ecommerce-core::emails.cancelled-order')
            ->from(Customsetting::get('site_from_email'), Customsetting::get('company_name'))
            ->subject(Translation::get('order-cancelled-email-subject', 'orders', 'Order #:orderId: has been cancelled', 'text', [
                'orderId' => $this->order->parentCreditOrder->invoice_id,
            ]))
            ->with([
                'order' => $this->order,
                'logo' => Customsetting::get('site_logo', Sites::getActive(), ''),
            ]);

        return $mail;
    }
}
