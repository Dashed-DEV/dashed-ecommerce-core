<?php

namespace Dashed\DashedEcommerceCore\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedTranslations\Models\Translation;

class ProductOnLowStockEmail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public Product $product;

    public function __construct(Product $product)
    {
        $this->product = $product;
    }

    public function build()
    {
        return $this->view(env('SITE_THEME', 'dashed') . '.emails.product-low-stock')
            ->from(Customsetting::get('site_from_email'), Customsetting::get('company_name'))
            ->subject(Translation::get('product-low-stock-email-subject', 'products', 'Product :productName: low on stock', 'text', [
                'productName' => $this->product->name,
            ]))
            ->with([
                'logo' => Customsetting::get('site_logo', Sites::getActive(), ''),
            ]);
    }
}
