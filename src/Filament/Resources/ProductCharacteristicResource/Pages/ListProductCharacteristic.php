<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ProductCharacteristicResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Concerns\Translatable;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductCharacteristicResource;

class ListProductCharacteristic extends ListRecords
{
    use Translatable;

    protected static string $resource = ProductCharacteristicResource::class;
}
