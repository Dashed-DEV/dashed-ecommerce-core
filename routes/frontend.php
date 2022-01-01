<?php

use Illuminate\Support\Facades\Route;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Qubiqx\QcommerceCore\Controllers\Frontend\FrontendController;
use Qubiqx\QcommerceCore\Middleware\FrontendMiddleware;
use Qubiqx\QcommerceCore\Middleware\GuestMiddleware;
use Qubiqx\QcommerceCore\Models\Customsetting;
use Qubiqx\QcommerceCore\Models\Translation;
use Qubiqx\QcommerceEcommerceCore\Controllers\Frontend\CartController;

Route::group(
    [
        'prefix' => LaravelLocalization::setLocale(),
        'middleware' => ['web', FrontendMiddleware::class, LocaleSessionRedirect::class, LaravelLocalizationRedirectFilter::class, LaravelLocalizationViewPath::class],
    ],
    function () {
        if (Customsetting::get('checkout_account') != 'disabled') {
            Route::group([
                'middleware' => [AuthMiddleware::class],
            ], function () {
                //Account routes
                Route::prefix('/' . Translation::get('account-slug', 'slug', 'account'))->group(function () {
                    Route::get('/' . Translation::get('account-orders-slug', 'slug', 'orders'), [AccountController::class, 'orders'])->name('qcommerce.frontend.account.orders');
                });
            });
        }

        //Cart routes
        Route::get('/' . Translation::get('cart-slug', 'slug', 'cart'), [CartController::class, 'cart'])->name('qcommerce.frontend.cart');
        Route::get('/' . Translation::get('checkout-slug', 'slug', 'checkout'), [CartController::class, 'checkout'])->name('qcommerce.frontend.checkout');
        Route::post('/' . Translation::get('checkout-slug', 'slug', 'checkout'), [CartController::class, 'startTransaction'])->name('qcommerce.frontend.start-transaction');
        Route::get('/' . Translation::get('complete-order-slug', 'slug', 'complete'), [CartController::class, 'complete'])->name('qcommerce.frontend.checkout.complete');
        Route::get('/download-invoice/{orderHash}', [CartController::class, 'downloadInvoice'])->name('qcommerce.frontend.download-invoice');
        Route::get('/download-packing-slip/{orderHash}', [CartController::class, 'downloadPackingSlip'])->name('qcommerce.frontend.download-packing-slip');
        Route::post('/apply-discount-code', [CartController::class, 'applyDiscountCode'])->name('qcommerce.frontend.cart.apply-discount-code');
        Route::post('/add-to-cart/{product}', [CartController::class, 'addToCart'])->name('qcommerce.frontend.cart.add-to-cart');
        Route::post('/update-to-cart/{rowId}', [CartController::class, 'updateToCart'])->name('qcommerce.frontend.cart.update-to-cart');
        Route::post('/remove-from-cart/{rowId}', [CartController::class, 'removeFromCart'])->name('qcommerce.frontend.cart.remove-from-cart');
    }
);