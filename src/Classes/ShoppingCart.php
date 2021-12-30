<?php

namespace Qubiqx\QcommerceEcommerceCore\Classes;

use Exception;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\App;
use Qubiqx\Qcommerce\Models\Product;
use Qubiqx\Qcommerce\Models\Translation;
use Gloudemans\Shoppingcart\Facades\Cart;
use Qubiqx\Qcommerce\Models\DiscountCode;
use Qubiqx\Qcommerce\Models\ShippingZone;
use Qubiqx\Qcommerce\Models\Customsetting;
use Qubiqx\Qcommerce\Models\PaymentMethod;
use Qubiqx\Qcommerce\Models\ShippingMethod;
use Rolandstarke\Thumbnail\Facades\Thumbnail;
use Qubiqx\Qcommerce\Models\ProductExtraOption;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

class ShoppingCart
{
    public static function getApplyDiscountCodeUrl()
    {
        return url(route('qcommerce.frontend.cart.apply-discount-code'));
    }

    public static function getAddToCartUrl(Product $product)
    {
        return url(route('qcommerce.frontend.cart.add-to-cart', ['product' => $product]));
    }

    public static function getRemoveFromCartUrl($item)
    {
        return url(route('qcommerce.frontend.cart.remove-from-cart', ['rowId' => $item->rowId]));
    }

    public static function getUpdateToCartUrl($item)
    {
        return url(route('qcommerce.frontend.cart.update-to-cart', ['rowId' => $item->rowId]));
    }

    public static function getCartUrl()
    {
        return LaravelLocalization::localizeUrl(route('qcommerce.frontend.cart'));
    }

    public static function getCheckoutUrl()
    {
        return LaravelLocalization::localizeUrl(route('qcommerce.frontend.checkout'));
    }

    public static function getStartTransactionUrl()
    {
        return url(route('qcommerce.frontend.start-transaction'));
    }

    public static function cartItems()
    {
        return Cart::content();
    }

    public static function cartItemsCount()
    {
        return Cart::count();
    }

    public static function totalDiscount($formatResult = false)
    {
        $totalDiscount = 0;

        $discountCode = session('discountCode');
        if ($discountCode) {
            $discountCode = DiscountCode::usable()->where('code', $discountCode)->first();

            if (!$discountCode || !$discountCode->isValidForCart()) {
                session(['discountCode' => '']);
            } else {
                if ($discountCode->type == 'percentage') {
                    $itemsInCart = self::cartItems();

                    foreach ($itemsInCart as $item) {
                        $discountedPrice = $discountCode->getDiscountedPriceForProduct($item);
                        $totalDiscount = $totalDiscount + (($item->price * $item->qty) - $discountedPrice);
                    }
                } elseif ($discountCode->type == 'amount') {
                    $totalDiscount = $discountCode->discount_amount;
                }
            }
        }

        if ($formatResult) {
            return CurrencyHelper::formatPrice($totalDiscount);
        } else {
            return number_format($totalDiscount, 2, '.', '');
        }
    }

    public static function subtotal($formatResult = false, $shippingMethodId = null, $paymentMethodId = null)
    {
        $cartTotal = self::total(false, false, $shippingMethodId, $paymentMethodId);

        $calculateInclusiveTax = Customsetting::get('taxes_prices_include_taxes');
        if (!$calculateInclusiveTax) {
            $cartTotal -= self::btw(false, false, $shippingMethodId, $paymentMethodId);
        }

        if ($formatResult) {
            return CurrencyHelper::formatPrice($cartTotal);
        } else {
            return number_format($cartTotal, 2, '.', '');
        }

        $cartTotal = 0;
        foreach (self::cartItems() as $cartItem) {
            $cartTotal = $cartTotal + ($cartItem->price * $cartItem->qty);
        }

        if ($shippingMethodId) {
            $shippingMethod = ShippingMethod::find($shippingMethodId);
            if ($shippingMethod) {
                $cartTotal += $shippingMethod->costsForCart;
            }
        }

        if ($paymentMethodId) {
            foreach (ShoppingCart::getPaymentMethods() as $paymentMethod) {
                if ($paymentMethod['id'] == $paymentMethodId) {
                    $cartTotal += $paymentMethod['extra_costs'];
                }
            }
        }

        if ($formatResult) {
            return CurrencyHelper::formatPrice($cartTotal);
        } else {
            return number_format($cartTotal, 2, '.', '');
        }
    }

    public static function total($formatResult = false, $calculateDiscount = true, $shippingMethodId = null, $paymentMethodId = null)
    {
        $cartTotal = 0;
        foreach (self::cartItems() as $cartItem) {
            $cartTotal = $cartTotal + ($cartItem->price * $cartItem->qty);
        }

        if ($calculateDiscount) {
            $cartTotal = $cartTotal - self::totalDiscount();
        }

        $calculateInclusiveTax = Customsetting::get('taxes_prices_include_taxes');
        if (!$calculateInclusiveTax) {
            $cartTotal = $cartTotal + self::btw(false, $calculateDiscount, $shippingMethodId, $paymentMethodId);
        }

        if ($shippingMethodId) {
            $shippingMethod = ShippingMethod::find($shippingMethodId);
            if ($shippingMethod) {
                $cartTotal += $shippingMethod->costsForCart;
            }
        }

        if ($paymentMethodId) {
            foreach (ShoppingCart::getPaymentMethods() as $paymentMethod) {
                if ($paymentMethod['id'] == $paymentMethodId) {
                    $cartTotal += $paymentMethod['extra_costs'];
                }
            }
        }

        if ($formatResult) {
            return CurrencyHelper::formatPrice($cartTotal);
        } else {
            return number_format($cartTotal, 2, '.', '');
        }
    }

    public static function depositAmount($formatResult = false, $calculateDiscount = true, $shippingMethodId = null, $paymentMethodId = null)
    {
        $depositAmount = 0;

        if ($paymentMethodId) {
            foreach (ShoppingCart::getPaymentMethods() as $paymentMethod) {
                if ($paymentMethod['id'] == $paymentMethodId) {
                    if ($paymentMethod['deposit_calculation']) {
                        $paymentMethod['deposit_calculation'] = str_replace('{ORDER_TOTAL_MINUS_PAYMENT_COSTS}', self::total(false, $calculateDiscount, $shippingMethodId, null), $paymentMethod['deposit_calculation']);
                        $depositAmount = eval('return ' . $paymentMethod['deposit_calculation'] . ';');
                    }
                }
            }
        }

        if ($formatResult) {
            return CurrencyHelper::formatPrice($depositAmount);
        } else {
            return number_format($depositAmount, 2, '.', '');
        }
    }

    public static function btw($formatResult = false, $calculateDiscount = true, $shippingMethodId = null, $paymentMethodId = null)
    {
        $calculateInclusiveTax = Customsetting::get('taxes_prices_include_taxes');
        $baseVatInfo = self::getVatBaseInfoForCalculation($calculateDiscount);
        $discountCode = $baseVatInfo['discountCode'];

        $taxTotal = $baseVatInfo['taxTotal'];

//        if ($calculateDiscount) {
//            $discountCode = DiscountCode::usable()->where('code', session('discountCode'))->first();
//
//            if (!$discountCode || !$discountCode->isValidForCart()) {
//                session(['discountCode' => '']);
//                $discountCode = null;
//            }
//        } else {
//            $discountCode = null;
//        }
//
//        $totalAmountForVats = [];
//
//        $totalPriceForProducts = 0;
//
//        foreach (self::cartItems() as $cartItem) {
//            if ($cartItem->model) {
//                if ($discountCode && $discountCode->type == 'percentage') {
//                    $price = $discountCode->getDiscountedPriceForProduct($cartItem);
//                } else {
//                    $price = $cartItem->price * $cartItem->qty;
//                }
//
//                $totalPriceForProducts += $price;
//
//                if ($calculateInclusiveTax) {
//                    $price = $price / (100 + $cartItem->model->vat_rate) * $cartItem->model->vat_rate;
//                } else {
//                    $price = $price / 100 * $cartItem->model->vat_rate;
//                }
//
//                $taxTotal += $price;
//                if ($cartItem->model->vat_rate > 0) {
//                    if (!isset($totalAmountForVats[$cartItem->model->vat_rate])) {
//                        $totalAmountForVats[$cartItem->model->vat_rate] = 0;
//                    }
//                    $totalAmountForVats[$cartItem->model->vat_rate] += ($cartItem->price * $cartItem->qty);
//                }
//            }
//        }
//
//        $vatPercentageOfTotals = [];
//
//        foreach ($totalAmountForVats as $percentage => $totalAmountForVat) {
//            $vatPercentageOfTotals[$percentage] = $totalAmountForVat > 0.00 ? ($totalAmountForVat / $totalPriceForProducts) * 100 : 0;
//        }

        if ($discountCode && $discountCode->type == 'amount') {
            if ($calculateInclusiveTax) {
                foreach ($baseVatInfo['vatPercentageOfTotals'] as $percentage => $vatPercentageOfTotal) {
                    if ($vatPercentageOfTotal) {
                        $taxTotal -= (($discountCode->discount_amount * ($vatPercentageOfTotal / 100)) / (100 + $percentage) * $percentage);
                    }
                }
            } else {
                foreach ($baseVatInfo['vatPercentageOfTotals'] as $percentage => $vatPercentageOfTotal) {
                    if ($vatPercentageOfTotal) {
                        $taxTotal -= (($discountCode->discount_amount * ($vatPercentageOfTotal / 100)) / 100 * $percentage);
                    }
                }
            }
        }

        if ($shippingMethodId) {
            $taxTotal += self::vatForShippingMethod($shippingMethodId, false, $calculateDiscount);
//            $shippingMethod = ShippingMethod::find($shippingMethodId);
//            if ($shippingMethod) {
//                if ($calculateInclusiveTax) {
//                    foreach ($vatPercentageOfTotals as $percentage => $vatPercentageOfTotal) {
//                        if ($vatPercentageOfTotal) {
//                            $taxTotal += (($shippingMethod->costs * ($vatPercentageOfTotal / 100)) / (100 + $percentage) * $percentage);
//                        }
//                    }
//                } else {
//                    foreach ($vatPercentageOfTotals as $percentage => $vatPercentageOfTotal) {
//                        if ($vatPercentageOfTotal) {
//                            $taxTotal += (($shippingMethod->costs * ($vatPercentageOfTotal / 100)) / 100 * $percentage);
//                        }
//                    }
//                }
//            }
        }

        if ($paymentMethodId) {
            $taxTotal += self::vatForPaymentMethod($paymentMethodId);
        }

        if ($formatResult) {
            return CurrencyHelper::formatPrice($taxTotal);
        } else {
            return number_format($taxTotal, 2, '.', '');
        }
    }

    public static function vatForShippingMethod($shippingMethodId, $formatResult = false, $calculateDiscount = true)
    {
        $calculateInclusiveTax = Customsetting::get('taxes_prices_include_taxes');

        $vatRate = self::vatRateForShippingMethod($shippingMethodId);
        $taxTotal = 0;

        $shippingMethod = ShippingMethod::find($shippingMethodId);
        if ($shippingMethod) {
            if ($calculateInclusiveTax) {
                $taxTotal += $shippingMethod->costsForCart / (100 + $vatRate) * $vatRate;
            } else {
                $taxTotal += $shippingMethod->costsForCart / 100 * $vatRate;
            }
        }

        return round($taxTotal, 2);
    }

    public static function vatRateForShippingMethod($shippingMethodId)
    {
        $baseVatInfo = self::getVatBaseInfoForCalculation();


        $shippingMethod = ShippingMethod::find($shippingMethodId);
        if ($shippingMethod && $baseVatInfo['vatRatesCount']) {
            return round($baseVatInfo['vatRates'] / $baseVatInfo['vatRatesCount'], 2);
        }

        return 0;
    }

    public static function vatForPaymentMethod($paymentMethodId)
    {
        $calculateInclusiveTax = Customsetting::get('taxes_prices_include_taxes');

        $taxTotal = 0;

        foreach (ShoppingCart::getPaymentMethods() as $paymentMethod) {
            if ($paymentMethod['id'] == $paymentMethodId) {
                if ($calculateInclusiveTax) {
                    $taxTotal = $taxTotal + ($paymentMethod['extra_costs'] / 121 * 21);
                } else {
                    $taxTotal = $taxTotal + ($paymentMethod['extra_costs'] / 100 * 21);
                }
            }
        }

        return $taxTotal;
    }

    public static function getVatBaseInfoForCalculation($calculateDiscount = true): array
    {
        $calculateInclusiveTax = Customsetting::get('taxes_prices_include_taxes');
        $taxTotal = 0;
        $vatRates = 0;
        $vatRatesCount = 0;

        if ($calculateDiscount) {
            $discountCode = DiscountCode::usable()->where('code', session('discountCode'))->first();

            if (!$discountCode || !$discountCode->isValidForCart()) {
                session(['discountCode' => '']);
                $discountCode = null;
            }
        } else {
            $discountCode = null;
        }

        $totalAmountForVats = [];

        $totalPriceForProducts = 0;

        foreach (self::cartItems() as $cartItem) {
            if ($cartItem->model) {
                if ($discountCode && $discountCode->type == 'percentage') {
                    $price = $discountCode->getDiscountedPriceForProduct($cartItem);
                } else {
                    $price = $cartItem->price * $cartItem->qty;
                }

                $totalPriceForProducts += $price;

                if ($calculateInclusiveTax) {
                    $price = $price / (100 + $cartItem->model->vat_rate) * $cartItem->model->vat_rate;
                } else {
                    $price = $price / 100 * $cartItem->model->vat_rate;
                }

                $taxTotal += $price;
                if ($cartItem->model->vat_rate > 0) {
                    if (!isset($totalAmountForVats[$cartItem->model->vat_rate])) {
                        $totalAmountForVats[$cartItem->model->vat_rate] = 0;
                    }
                    $totalAmountForVats[$cartItem->model->vat_rate] += ($cartItem->price * $cartItem->qty);
                    $vatRates += $cartItem->model->vat_rate * $cartItem->qty;
                    $vatRatesCount += $cartItem->qty;
                }
            }
        }

        $vatPercentageOfTotals = [];

        foreach ($totalAmountForVats as $percentage => $totalAmountForVat) {
            $vatPercentageOfTotals[$percentage] = $totalAmountForVat > 0.00 ? ($totalAmountForVat / $totalPriceForProducts) * 100 : 0;
        }

        return [
            'vatPercentageOfTotals' => $vatPercentageOfTotals,
            'vatRates' => $vatRates,
            'vatRatesCount' => $vatRatesCount,
            'taxTotal' => $taxTotal,
            'discountCode' => $discountCode,
        ];
    }

    public static function getAvailableShippingMethods($countryName, $formatResult = false)
    {
        $shippingZones = ShippingZone::get();
        foreach ($shippingZones as $shippingZone) {
            $shippingZoneIsActive = false;
            foreach (json_decode($shippingZone->zones, true) as $zone) {
                foreach (Countries::getCountries() as $country) {
                    if ($country['name'] == $zone['id']) {
                        if (strtolower($country['name']) == strtolower($countryName)) {
                            $shippingZoneIsActive = true;
                        }
                        if (strtolower($country['alpha2Code']) == strtolower($countryName)) {
                            $shippingZoneIsActive = true;
                        }
                        if (strtolower($country['alpha3Code']) == strtolower($countryName)) {
                            $shippingZoneIsActive = true;
                        }
                        if (strtolower($country['demonym']) == strtolower($countryName)) {
                            $shippingZoneIsActive = true;
                        }
                        foreach ($country['altSpellings'] as $altSpelling) {
                            if (strlen($countryName) > 5) {
                                if (Str::contains(strtolower($altSpelling), strtolower($countryName))) {
                                    $shippingZoneIsActive = true;
                                }
                            } else {
                                if (strtolower($altSpelling) == strtolower($countryName)) {
                                    $shippingZoneIsActive = true;
                                }
                            }
                        }
                    }
                }
            }

            if (!$shippingZoneIsActive && $shippingZone->search_fields) {
                $searchFields = explode(',', $shippingZone->search_fields);
                foreach ($searchFields as $searchField) {
                    $searchField = trim($searchField);
                    if (strtolower($searchField) == strtolower($countryName)) {
                        $shippingZoneIsActive = true;
                    }
                }
            }

            if ($shippingZoneIsActive) {
                $shippingMethods = $shippingZone->shippingMethods()->where('minimum_order_value', '<=', self::total())->where('maximum_order_value', '>', self::total())->orderBy('order', 'ASC')->get();
                foreach ($shippingMethods as $shippingMethod) {
                    $shippingMethod->correctName = $shippingMethod->getTranslation('name', App::getLocale());
                    $costs = $shippingMethod->costsForCart;
                    $cartItems = self::cartItems();
                    foreach ($shippingMethod->shippingMethodClasses as $shippingClass) {
                        foreach ($cartItems as $cartItem) {
                            if ($cartItem->model->shippingClasses->contains($shippingClass->id)) {
                                $costs = $costs + ($shippingClass->costs * $cartItem->qty);
                            }
                        }
                    }
                    $shippingMethod->costs = $costs;
                    if ($shippingMethod->costs == 0) {
                        $shippingMethod->costsFormatted = Translation::get('free', 'checkout', 'Gratis');
                    } else {
                        $shippingMethod->costsFormatted = CurrencyHelper::formatPrice($costs);
                    }
                }

                return $shippingMethods;
            }
        }

        return [];
    }

    public static function getAvailablePaymentMethods($countryName, $formatResult = false)
    {
        $paymentMethods = self::getPaymentMethods();
        $shippingZones = ShippingZone::get();
        foreach ($shippingZones as $shippingZone) {
            $shippingZoneIsActive = false;
            foreach (json_decode($shippingZone->zones, true) as $zone) {
                foreach (Countries::getCountries() as $country) {
                    if ($country['name'] == $zone['id']) {
                        if (strtolower($country['name']) == strtolower($countryName)) {
                            $shippingZoneIsActive = true;
                        }
                        if (strtolower($country['alpha2Code']) == strtolower($countryName)) {
                            $shippingZoneIsActive = true;
                        }
                        if (strtolower($country['alpha3Code']) == strtolower($countryName)) {
                            $shippingZoneIsActive = true;
                        }
                        if (strtolower($country['demonym']) == strtolower($countryName)) {
                            $shippingZoneIsActive = true;
                        }
                        foreach ($country['altSpellings'] as $altSpelling) {
                            if (strlen($countryName) > 5) {
                                if (Str::contains(strtolower($altSpelling), strtolower($countryName))) {
                                    $shippingZoneIsActive = true;
                                }
                            } else {
                                if (strtolower($altSpelling) == strtolower($countryName)) {
                                    $shippingZoneIsActive = true;
                                }
                            }
                        }
                    }
                }
            }

            if (!$shippingZoneIsActive && $shippingZone->search_fields) {
                $searchFields = explode(',', $shippingZone->search_fields);
                foreach ($searchFields as $searchField) {
                    if (strtolower($searchField) == strtolower($countryName)) {
                        $shippingZoneIsActive = true;
                    }
                }
            }

            if ($shippingZoneIsActive && $shippingZone->disabled_payment_method_ids) {
                $disabledPaymentMethodIds = json_decode($shippingZone->disabled_payment_method_ids, true);
                if (is_array($disabledPaymentMethodIds)) {
                    foreach ($disabledPaymentMethodIds as $disabledPaymentMethod) {
                        foreach ($paymentMethods as $key => $paymentMethod) {
                            if ($disabledPaymentMethod['id'] == $paymentMethod['id']) {
                                unset($paymentMethods[$key]);
                            }
                        }
                    }
                }
            }
        }

        return $paymentMethods;
    }

    public static function getPaymentMethods()
    {
        $paymentMethods = [];
        foreach (PaymentMethod::where('available_from_amount', '<', self::total())->get() as $paymentMethod) {
            $paymentMethods[] = [
                'id' => $paymentMethod->id,
                'system' => 'own',
                'name' => $paymentMethod->name,
                'image' => [],
                'postpay' => false,
                'extra_costs' => $paymentMethod->extra_costs,
                'additional_info' => $paymentMethod->additional_info,
                'payment_instructions' => $paymentMethod->payment_instructions,
                'deposit_calculation' => $paymentMethod->deposit_calculation
            ];
        }

        if (Customsetting::get('mollie_connected')) {
            foreach (Mollie::getPaymentMethods() as $paymentMethod) {
                if ($paymentMethod->active) {
                    $paymentMethods[] = [
                        'id' => 'mollie_' . $paymentMethod->id,
                        'system' => 'mollie',
                        'image' => [],
                        'name' => $paymentMethod->description,
                        'postpay' => false,
                        'extra_costs' => Customsetting::get('mollie_payment_method_costs_' . $paymentMethod->id, Sites::getActive(), 0),
                        'additional_info' => '',
                        'payment_instructions' => '',
                        'deposit_calculation' => ''
                    ];
                }
            }
        }

        if (Customsetting::get('paynl_connected')) {
            foreach (PayNL::getPaymentMethods() as $paymentMethod) {
                if ($paymentMethod['active'] && ($paymentMethod['min_amount'] / 100) <= self::total() && ($paymentMethod['max_amount'] / 100) >= self::total()) {
                    $paymentMethods[] = [
                        'id' => 'paynl_' . $paymentMethod['id'],
                        'system' => 'paynl',
                        'image' => [
                            'original' => 'https://static.pay.nl/' . $paymentMethod['brand']['image'],
                            '20' => Thumbnail::src('https://static.pay.nl/' . $paymentMethod['brand']['image'])->widen(20)->url(true),
                            '25' => Thumbnail::src('https://static.pay.nl/' . $paymentMethod['brand']['image'])->widen(25)->url(true),
                            '50' => Thumbnail::src('https://static.pay.nl/' . $paymentMethod['brand']['image'])->widen(50)->url(true),
                            '100' => Thumbnail::src('https://static.pay.nl/' . $paymentMethod['brand']['image'])->widen(100)->url(true),
                        ],
                        'name' => $paymentMethod['visibleName'],
                        'postpay' => $paymentMethod['postpay'],
                        'extra_costs' => Customsetting::get('paynl_payment_method_costs_' . $paymentMethod['id'], Sites::getActive(), 0),
                        'additional_info' => Customsetting::get('paynl_payment_method_additional_info_' . $paymentMethod['id'], Sites::getActive()),
                        'payment_instructions' => Customsetting::get('paynl_payment_method_payment_instructions_' . $paymentMethod['id'], Sites::getActive()),
                        'deposit_calculation' => ''
                    ];
                }
            }
        }

        return $paymentMethods;
    }

    public static function getAllPaymentMethods()
    {
        $paymentMethods = [];
        foreach (PaymentMethod::get() as $paymentMethod) {
            $paymentMethods[] = [
                'id' => $paymentMethod->id,
                'system' => 'own',
                'name' => $paymentMethod->name,
                'image' => [],
                'postpay' => false,
                'extra_costs' => $paymentMethod->extra_costs,
                'additional_info' => $paymentMethod->additional_info,
                'payment_instructions' => $paymentMethod->payment_instructions,
                'deposit_calculation' => $paymentMethod->deposit_calculation,
            ];
        }
        if (Customsetting::get('mollie_connected')) {
            foreach (Mollie::getPaymentMethods() as $paymentMethod) {
                $paymentMethods[] = [
                    'id' => 'mollie_' . $paymentMethod->id,
                    'system' => 'mollie',
                    'image' => [],
                    'name' => $paymentMethod->description,
                    'postpay' => false,
                    'extra_costs' => Customsetting::get('mollie_payment_method_costs_' . $paymentMethod->id, Sites::getActive(), 0),
                    'additional_info' => '',
                    'payment_instructions' => '',
                    'deposit_calculation' => '',
                ];
            }
        }
        if (Customsetting::get('paynl_connected')) {
            foreach (PayNL::getPaymentMethods() as $paymentMethod) {
                $paymentMethods[] = [
                    'id' => 'paynl_' . $paymentMethod['id'],
                    'system' => 'paynl',
                    'image' => [
                        'original' => 'https://static.pay.nl/' . $paymentMethod['brand']['image'],
                        '20' => Thumbnail::src('https://static.pay.nl/' . $paymentMethod['brand']['image'])->widen(20)->url(true),
                        '25' => Thumbnail::src('https://static.pay.nl/' . $paymentMethod['brand']['image'])->widen(25)->url(true),
                        '50' => Thumbnail::src('https://static.pay.nl/' . $paymentMethod['brand']['image'])->widen(50)->url(true),
                        '100' => Thumbnail::src('https://static.pay.nl/' . $paymentMethod['brand']['image'])->widen(100)->url(true),
                    ],
                    'name' => $paymentMethod['visibleName'],
                    'postpay' => $paymentMethod['postpay'],
                    'extra_costs' => Customsetting::get('paynl_payment_method_costs_' . $paymentMethod['id'], Sites::getActive(), 0),
                    'additional_info' => Customsetting::get('paynl_payment_method_additional_info_' . $paymentMethod['id'], Sites::getActive()),
                    'payment_instructions' => Customsetting::get('paynl_payment_method_payment_instructions_' . $paymentMethod['id'], Sites::getActive()),
                    'deposit_calculation' => '',
                ];
            }
        }

        return $paymentMethods;
    }

    public static function getPaymentMethodsForDeposit($paymentMethodId)
    {
        $paymentMethods = self::getAllPaymentMethods();

        foreach ($paymentMethods as $key => $paymentMethod) {
            if ($paymentMethod['system'] == 'own') {
                unset($paymentMethods[$key]);
            } else {
                if (!(Customsetting::get("payment_method_{$paymentMethodId}_deposit_payment_method_{$paymentMethod['id']}") ? true : false)) {
                    unset($paymentMethods[$key]);
                }
            }
        }

        return $paymentMethods;
    }

    public static function removeInvalidItems()
    {
        $discountCode = DiscountCode::usable()->where('code', session('discountCode'))->first();

        if (!$discountCode || !$discountCode->isValidForCart()) {
            session(['discountCode' => '']);
        }

        $cartItems = self::cartItems();

        foreach ($cartItems as $cartItem) {
            $cartItemDeleted = false;

            if (!$cartItem->model) {
                Cart::remove($cartItem->rowId);
                $cartItemDeleted = true;
            } elseif ($cartItem->model->stock() < $cartItem->qty) {
                Cart::remove($cartItem->rowId);
                $cartItemDeleted = true;
            } elseif ($cartItem->model->limit_purchases_per_customer && $cartItem->qty > $cartItem->model->limit_purchases_per_customer_limit) {
                Cart::update($cartItem->rowId, $cartItem->model->limit_purchases_per_customer_limit);
            }

            if (!$cartItemDeleted) {
                $productPrice = $cartItem->model->currentPrice;
                $options = [];

                foreach ($cartItem->options as $productExtraOptionId => $productExtraOption) {
                    if (!$cartItemDeleted) {
                        $thisProductExtraOption = ProductExtraOption::find($productExtraOptionId);
                        if ($thisProductExtraOption) {
                            $options[$productExtraOptionId] = [
                                'name' => $productExtraOption['name'],
                                'value' => $thisProductExtraOption->value,
                            ];
                            if ($thisProductExtraOption->calculate_only_1_quantity) {
                                $productPrice = $productPrice + ($thisProductExtraOption->price / $cartItem->qty);
                            } else {
                                $productPrice = $productPrice + $thisProductExtraOption->price;
                            }
                        } else {
                            Cart::remove($cartItem->rowId);
                            $cartItemDeleted = true;
                        }
                    }
                }
                if (!$cartItemDeleted) {
                    $cartItem->price = $productPrice;

                    foreach ($cartItems as $otherCartItem) {
                        try {
                            Cart::get($cartItem->rowId);
                            $cartItemExists = true;
                        } catch (Exception $exception) {
                            $cartItemExists = false;
                        }
                        try {
                            Cart::get($otherCartItem->rowId);
                            $otherCartItemExists = true;
                        } catch (Exception $exception) {
                            $otherCartItemExists = false;
                        }

                        if ($cartItemExists && $otherCartItemExists) {
                            if ($cartItem->rowId != $otherCartItem->rowId) {
                                if ($cartItem->model->id == $otherCartItem->model->id) {
                                    if ($cartItem->options == $otherCartItem->options) {
                                        $newQuantity = $cartItem->qty + $otherCartItem->qty;

                                        if ($cartItem->model->limit_purchases_per_customer && $newQuantity > $cartItem->model->limit_purchases_per_customer_limit) {
                                            Cart::update($cartItem->rowId, $cartItem->model->limit_purchases_per_customer_limit);
                                            Cart::remove($otherCartItem->rowId);
                                        } else {
                                            Cart::update($cartItem->rowId, $newQuantity);
                                            Cart::remove($otherCartItem->rowId);
                                        }

                                    } else {
                                        $hasOnlySingleOptionExtras = true;
                                        $optionsForBothItems = [];

                                        foreach ($cartItem->options as $key => $option) {
                                            $productExtraOption = ProductExtraOption::find($key);
                                            if (!$productExtraOption->calculate_only_1_quantity) {
                                                $hasOnlySingleOptionExtras = false;
                                            }
                                            if (!isset($optionsForBothItems[$key])) {
                                                $optionsForBothItems[$key] = $option;
                                            }
                                        }
                                        foreach ($otherCartItem->options as $key => $option) {
                                            $productExtraOption = ProductExtraOption::find($key);
                                            if (!$productExtraOption->calculate_only_1_quantity) {
                                                $hasOnlySingleOptionExtras = false;
                                            }
                                            if (!isset($optionsForBothItems[$key])) {
                                                $optionsForBothItems[$key] = $option;
                                            }
                                        }

                                        if ($hasOnlySingleOptionExtras) {
                                            $newQuantity = $cartItem->qty + $otherCartItem->qty;

                                            if ($cartItem->model->limit_purchases_per_customer && $newQuantity > $cartItem->model->limit_purchases_per_customer_limit) {
                                                Cart::remove($cartItem->rowId);
                                                Cart::remove($otherCartItem->rowId);
                                                Cart::add($cartItem->model->id, $cartItem->model->name, $cartItem->model->limit_purchases_per_customer_limit, $productPrice, $optionsForBothItems)->associate(Product::class);
                                            } else {
                                                Cart::remove($cartItem->rowId);
                                                Cart::remove($otherCartItem->rowId);
                                                Cart::add($cartItem->model->id, $cartItem->model->name, $newQuantity, $productPrice, $optionsForBothItems)->associate(Product::class);
                                            }
                                        }
                                    }


//                                $newQuantity = $cartItem->qty + $quantity;
//
//                                if ($product->limit_purchases_per_customer && $newQuantity > $cartItem->model->limit_purchases_per_customer_limit) {
//                                    Cart::update($cartItem->rowId, $cartItem->model->limit_purchases_per_customer_limit);
//
//                                    ShoppingCart::removeInvalidItems();
//                                    return redirect()->back()->with('error', Translation::get('product-only-1-purchase-per-customer', 'cart', 'You can only purchase one of this product'))->withInput();
//                                }
//
//                                Cart::update($cartItem->rowId, $newQuantity);
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    public static function emptyMyCart()
    {
        session(['discountCode' => '']);
        Cart::destroy();
    }

    public static function hasCartitemByRowId($rowId)
    {
        try {
            Cart::get($rowId);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public static function getCheckoutData($shippingMethodId, $paymentMethodId)
    {
        $subTotal = ShoppingCart::subtotal(false, $shippingMethodId, $paymentMethodId);
        $discount = ShoppingCart::totalDiscount();
        $btw = ShoppingCart::btw(false, true, $shippingMethodId, $paymentMethodId);
        $depositAmount = ShoppingCart::depositAmount(false, true, $shippingMethodId, $paymentMethodId);
        $total = ShoppingCart::total(false, true, $shippingMethodId, $paymentMethodId);
        $depositPaymentMethods = [];
        if ($depositAmount > 0.00) {
            $depositPaymentMethods = ShoppingCart::getPaymentMethodsForDeposit($paymentMethodId);
        }
        $shippingCosts = 0;
        $paymentCosts = 0;

        if ($shippingMethodId) {
            $shippingMethod = ShippingMethod::find($shippingMethodId);
            if ($shippingMethod) {
                $shippingCosts = $shippingMethod->costsForCart;
            }
        }

        if ($paymentMethodId) {
            foreach (ShoppingCart::getPaymentMethods() as $paymentMethod) {
                if ($paymentMethod['id'] == $paymentMethodId) {
                    $paymentCosts = $paymentMethod['extra_costs'];
                }
            }
        }

        return [
            'subTotal' => $subTotal,
            'subTotalFormatted' => CurrencyHelper::formatPrice($subTotal),
            'discount' => $discount,
            'discountFormatted' => CurrencyHelper::formatPrice($discount),
            'btw' => $btw,
            'btwFormatted' => CurrencyHelper::formatPrice($btw),
            'total' => $total,
            'totalFormatted' => CurrencyHelper::formatPrice($total),
            'shippingCosts' => $shippingCosts,
            'shippingCostsFormatted' => CurrencyHelper::formatPrice($shippingCosts),
            'paymentCosts' => $paymentCosts,
            'paymentCostsFormatted' => CurrencyHelper::formatPrice($paymentCosts),
            'postpayPaymentMethod' => PaymentMethods::isPostpayMethod($paymentMethodId),
            'depositRequired' => $depositAmount > 0.00 ? true : false,
            'depositAmount' => $depositAmount,
            'depositAmountFormatted' => CurrencyHelper::formatPrice($depositAmount),
            'depositPaymentMethods' => $depositPaymentMethods
        ];
    }
}
