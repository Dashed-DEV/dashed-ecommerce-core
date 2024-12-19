<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ProductResource\Pages;

use Dashed\DashedEcommerceCore\Models\Product;
use Filament\Actions\CreateAction;
use Filament\Actions\LocaleSwitcher;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Concerns\Translatable;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductResource;
use Illuminate\Database\Eloquent\Builder;

class ListProducts extends ListRecords
{
    use Translatable;

    protected static string $resource = ProductResource::class;

    protected ?string $maxContentWidth = 'full';

    protected function getTableQuery(): ?Builder
    {
        return Product::query();
    }

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
            CreateAction::make(),
        ];
    }
}
