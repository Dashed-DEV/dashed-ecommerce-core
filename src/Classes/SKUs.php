<?php

namespace Dashed\DashedEcommerceCore\Classes;

class SKUs
{
    public static function hideOnPackingSlip()
    {
        return ['payment_costs', 'shipping_costs'];
    }

    public static function hideOnConfirmationEmail()
    {
        return ['payment_costs', 'shipping_costs'];
    }
}
