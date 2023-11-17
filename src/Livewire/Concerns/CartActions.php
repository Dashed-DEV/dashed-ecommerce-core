<?php

namespace Dashed\DashedEcommerceCore\Livewire\Concerns;

use Filament\Notifications\Notification;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Classes\ShoppingCart;

trait CartActions
{
    public function checkCart(?string $status = null, ?string $message = null)
    {
        if ($status) {
            if($status == 'error') {
                $status = 'danger';
            }

            Notification::make()
                ->$status()
                ->title($message)
                ->send();
        }

        ShoppingCart::removeInvalidItems();

        $this->dispatch('refreshCart');
    }

    public function changeQuantity(string $rowId, int $quantity)
    {
        if (! $quantity) {
            if (ShoppingCart::hasCartitemByRowId($rowId)) {
                \Gloudemans\Shoppingcart\Facades\Cart::remove($rowId);
            }

            $this->checkCart('success', Translation::get('product-removed-from-cart', 'cart', 'The product has been removed from your cart'));
        } else {
            if (ShoppingCart::hasCartitemByRowId($rowId)) {
                $cartItem = \Gloudemans\Shoppingcart\Facades\Cart::get($rowId);
                \Gloudemans\Shoppingcart\Facades\Cart::update($rowId, ($quantity));
            }

            $this->checkCart('success', Translation::get('product-updated-to-cart', 'cart', 'The product has been updated to your cart'));
        }

        $this->fillPrices();
    }

    public function applyDiscountCode()
    {
        if (! $this->discountCode) {
            session(['discountCode' => '']);
            $this->discountCode = '';
            $this->discount = 0;
            $this->fillPrices();

            return $this->checkCart('danger', Translation::get('discount-code-not-valid', 'cart', 'The discount code is not valid'));
        }

        $discountCode = DiscountCode::usable()->where('code', $this->discountCode)->first();

        if (! $discountCode || ! $discountCode->isValidForCart()) {
            session(['discountCode' => '']);
            $this->discountCode = '';
            $this->fillPrices();

            return $this->checkCart('danger', Translation::get('discount-code-not-valid', 'cart', 'The discount code is not valid'));
        }

        session(['discountCode' => $discountCode->code]);
        $this->fillPrices();

        return $this->checkCart('success', Translation::get('discount-code-applied', 'cart', 'The discount code has been applied and discount has been calculated'));
    }

    public function fillPrices()
    {
        $checkoutData = ShoppingCart::getCheckoutData($this->shippingMethod, $this->paymentMethod);
        $this->subtotal = $checkoutData['subTotal'];
        $this->discount = $checkoutData['discount'];
        $this->tax = $checkoutData['btw'];
        $this->total = $checkoutData['total'];
        $this->shippingCosts = $checkoutData['shippingCosts'];
        $this->paymentCosts = $checkoutData['paymentCosts'];
        $this->depositAmount = $checkoutData['depositAmount'];
        $this->depositPaymentMethods = $checkoutData['depositPaymentMethods'];
        $this->postpayPaymentMethod = $checkoutData['postpayPaymentMethod'];
    }
}
