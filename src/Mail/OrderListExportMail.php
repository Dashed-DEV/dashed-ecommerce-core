<?php

namespace Dashed\DashedEcommerceCore\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedTranslations\Models\Translation;
use Illuminate\Support\Facades\Storage;

class OrderListExportMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public string $hash;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(string $hash)
    {
        $this->hash = $hash;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $orderListPath = Storage::disk('public')->url('dashed/tmp-exports/' . $this->hash . '/order-lists/order-list.xlsx');

        return $this->view('dashed-ecommerce-core::emails.exported-order-list')
            ->from(Customsetting::get('site_from_email'), Customsetting::get('company_name'))
            ->subject(Translation::get('exported-order-list-email-subject', 'orders', 'Exported order list'))
            ->attach($orderListPath, [
                'as' => Customsetting::get('company_name') . ' - exported order list.xlsx',
                'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
    }
}
