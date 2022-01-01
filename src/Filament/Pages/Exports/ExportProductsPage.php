<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Pages\Exports;

use Filament\Pages\Page;
use Maatwebsite\Excel\Facades\Excel;
use Filament\Forms\Contracts\HasForms;
use Illuminate\Support\Facades\Storage;
use Filament\Forms\Concerns\InteractsWithForms;
use Qubiqx\QcommerceEcommerceCore\Models\Product;
use Qubiqx\QcommerceEcommerceCore\Exports\ProductListExport;

class ExportProductsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cloud-download';
    protected static ?string $navigationLabel = 'Exporteer producten';
    protected static ?string $navigationGroup = 'Export';
    protected static ?string $title = 'Exporteer producten';
    protected static ?int $navigationSort = 100000;

    protected static string $view = 'qcommerce-ecommerce-core::exports.pages.export-products';

    protected function getFormSchema(): array
    {
        return [
        ];
    }

    public function submit()
    {
        $products = Product::notParentProduct()
            ->search()
            ->latest()
            ->get();

        Excel::store(new ProductListExport($products), '/exports/product-lists/product-list.xlsx');

        $this->notify('success', 'De export is gedownload');

        return Storage::download('/exports/product-lists/product-list.xlsx');
    }
}