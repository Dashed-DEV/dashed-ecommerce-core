<?php

namespace Qubiqx\QcommerceEcommerceCore\Classes;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;
use Qubiqx\QcommerceEcommerceCore\Models\Product;
use Qubiqx\QcommerceTranslations\Models\Translation;

class ProductRouteHandler
{
    public static function handle($parameters = [])
    {
        $slug = $parameters['slug'] ?? '';
        $slugComponents = explode('/', $slug);

        if ($slugComponents[0] == Translation::get('products-slug', 'slug', 'products') && count($slugComponents) == 2) {
            $product = Product::thisSite()->where('slug->' . App::getLocale(), $slugComponents[1]);
            if (! auth()->check() || auth()->user()->role != 'admin') {
                $product->publicShowable();
            }
            $product = $product->first();

            if (! $product) {
                foreach (Product::thisSite()->publicShowable()->get() as $possibleProduct) {
                    if (! $product && $possibleProduct->slug == $slugComponents[1]) {
                        $product = $possibleProduct;
                    }
                }
            }

            if ($product) {
                if (View::exists('qcommerce.products.show')) {
                    frontend()->metaData('metaTitle', $product->meta_title ?: $product->name);
                    frontend()->metaData('metaDescription', $product->meta_description);
                    $metaImage = $product->meta_image;
                    if (! $metaImage) {
                        $metaImage = $product->firstMediaUrl;
                    }
                    if ($metaImage) {
                        frontend()->metaData('metaImage', $metaImage);
                    }

                    View::share('product', $product);

                    return view('qcommerce.products.show');
                } else {
                    return 'pageNotFound';
                }
            }
        }
    }
}
