<?php

namespace Dashed\DashedEcommerceCore\Mail;

use Dashed\DashedCore\Classes\Sites;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedTranslations\Models\Translation;

class AdminPreOrderConfirmationMail extends Mailable
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
        $invoicePath = Storage::disk('dashed')->url('dashed/invoices/invoice-' . $this->order->invoice_id . '-' . $this->order->hash . '.pdf');

        return $this->view('dashed-ecommerce-core::emails.admin-confirm-order')
            ->from(Customsetting::get('site_from_email'), Customsetting::get('company_name'))->subject(Translation::get('admin-order-confirmation-email-subject', 'orders', 'Order received #:orderId:', 'text', [
                'orderId' => $this->order->invoice_id,
            ]))
            ->with([
                'order' => $this->order,
                'logo' => Customsetting::get('site_logo', Sites::getActive(), '')
            ])->attach($invoicePath, [
                'as' => Customsetting::get('company_name') . ' - ' . $this->order->invoice_id . '.pdf',
                'mime' => 'application/pdf',
            ]);
    }
}
