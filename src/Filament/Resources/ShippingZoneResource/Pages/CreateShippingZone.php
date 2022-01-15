<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources\ShippingZoneResource\Pages;

use Qubiqx\QcommerceCore\Classes\Sites;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ShippingZoneResource;

class CreateShippingZone extends CreateRecord
{
    use Translatable;

    protected static string $resource = ShippingZoneResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['site_id'] = $data['site_id'] ?? Sites::getFirstSite()['id'];

        return $data;
    }
}
