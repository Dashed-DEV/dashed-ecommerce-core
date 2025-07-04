@if(env('APP_ENV') != 'local')
    @if(isset($product))
        <x-dashed-ecommerce-core::frontend.products.schema
            :product="$product"></x-dashed-ecommerce-core::frontend.products.schema>
    @endif
    @if(isset($products))
        @foreach($products as $product)
            <x-dashed-ecommerce-core::frontend.products.schema
                :product="$product"></x-dashed-ecommerce-core::frontend.products.schema>
        @endforeach
    @endif

    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('productAddedToCart', (event) => {
                @if(Customsetting::get('google_tagmanager_id'))
                dataLayer.push({
                    'event': 'add_to_cart',
                    'ecommerce': {
                        'currency': 'EUR',
                        'cartTotal': event[0].cartTotal,
                        'items': {
                            'products': [{
                                'name': event[0].productName,
                                'id': event[0].product.id,
                                'price': event[0].price,
                                'quantity': event[0].quantity,
                            }]
                        }
                    }
                });
                @endif
                @if(Customsetting::get('facebook_pixel_conversion_id') || Customsetting::get('facebook_pixel_site_id'))
                fbq('track', 'AddToCart');
                @endif
            });

            Livewire.on('productRemovedFromCart', (event) => {
                @if(Customsetting::get('google_tagmanager_id'))
                dataLayer.push({
                    'event': 'remove_from_cart',
                    'ecommerce': {
                        'currency': 'EUR',
                        'cartTotal': event[0].cartTotal,
                        'items': {
                            'products': [{
                                'name': event[0].productName,
                                'id': event[0].product.id,
                                'price': event[0].price,
                            }]
                        }
                    }
                });
                @endif
            });

            Livewire.on('checkoutInitiated', (event) => {
                @if(Customsetting::get('facebook_pixel_conversion_id') || Customsetting::get('facebook_pixel_site_id'))
                fbq('track', 'InitiateCheckout');
                @endif
                @if(Customsetting::get('google_tagmanager_id'))
                dataLayer.push({
                    'event': 'begin_checkout',
                    'ecommerce': {
                        'currency': 'EUR',
                        'value': event[0].cartTotal,
                        'items': event[0].items
                    }
                });
                @endif
            });

            Livewire.on('cartInitiated', (event) => {
                @if(Customsetting::get('google_tagmanager_id'))
                dataLayer.push({
                    'event': 'view_cart',
                    'ecommerce': {
                        'currency': 'EUR',
                        'value': event[0].cartTotal,
                        'items': event[0].items
                    }
                });
                @endif
            });

            Livewire.on('viewProduct', (event) => {
                @if(Customsetting::get('google_tagmanager_id'))
                dataLayer.push({
                    'event': 'view_item',
                    'ecommerce': {
                        'currency': 'EUR',
                        'value': event[0].cartTotal,
                        'items': {
                            'products': [{
                                'name': event[0].productName,
                                'id': event[0].product.id,
                                'price': event[0].price,
                            }]
                        }
                    }
                });
                @endif
            });

            Livewire.on('checkoutSubmitted', (event) => {
                @if(Customsetting::get('facebook_pixel_conversion_id') || Customsetting::get('facebook_pixel_site_id'))
                fbq('track', 'AddPaymentInfo');
                @endif
            });

            Livewire.on('orderPaid', (event) => {
                @if(Customsetting::get('facebook_pixel_conversion_id') || Customsetting::get('facebook_pixel_site_id'))
                fbq('track', 'Purchase', {currency: "EUR", value: event.total});
                @endif
                @if(Customsetting::get('google_tagmanager_id'))
                dataLayer.push({
                    'event': 'purchase',
                    'ecommerce': {
                        'currency': 'EUR',
                        'value': event[0].total,
                        'transaction_id': event[0].orderId,
                        'items': event[0].items,
                        'coupoon': event[0].discountCode,
                        'tax': event[0].tax,
                    }
                });
                @endif
            });
        });
    </script>
@endif
