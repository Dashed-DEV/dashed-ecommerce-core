<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Dashed\DashedEcommerceCore\Models\ShippingZone;

class ShippingZones
{
    public static function get()
    {
        $allShippingZones = ShippingZone::get();

        return $allShippingZones;
    }

    public static function getActiveRegions()
    {
        $regions = [];
        $allShippingZones = ShippingZone::get();

        foreach ($allShippingZones as $allShippingZone) {
            if ($allShippingZone->zones) {
                foreach ($allShippingZone->zones as $zone) {
                    $regions[] = [
                        'name' => $zone,
                        'value' => $zone,
                    ];
                }
            }
        }

        return $regions;
    }
}
