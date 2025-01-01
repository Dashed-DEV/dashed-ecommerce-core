<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\PricePerUserResource\Pages;

use Dashed\DashedEcommerceCore\Imports\PricePerProductForUserImport;
use Filament\Actions\Action;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Forms\Components\FileUpload;
use Illuminate\Contracts\Support\Htmlable;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Exports\PricePerProductForUserExport;
use Dashed\DashedEcommerceCore\Filament\Resources\PricePerUserResource;

class EditPricePerUser extends EditRecord
{
    protected static string $resource = PricePerUserResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Bewerk prijzen voor  ' . $this->record->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Exporteer')
                ->icon('heroicon-s-arrow-down-tray')
                ->action(function () {
                    Notification::make()
                        ->title('Exporteren')
                        ->body('Het exporteren is gelukt.')
                        ->success()
                        ->send();

                    return Excel::download(new PricePerProductForUserExport($this->record), 'Prijzen voor ' . $this->record->name . '.xlsx');
                }),
            Action::make('import')
                ->label('Importeer')
                ->icon('heroicon-s-arrow-up-tray')
                ->form([
                    FileUpload::make('file')
                        ->label('Bestand')
                        ->disk('local')
                        ->directory('imports')
                        ->rules([
                            'required',
                            'file',
                            'mimes:csv,xlsx',
                        ]),
                ])
                ->action(function ($data) {

                    $file = Storage::disk('local')->path($data['file']);
                    Excel::import(new PricePerProductForUserImport($this->record), $file);

                    Notification::make()
                        ->title('Importeren')
                        ->body('Het importeren is gelukt, refresh de pagina.')
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $products = Product::all();

        $data['product_ids'] = DB::table('dashed__product_user')
            ->where('user_id', $this->record->id)
            ->pluck('product_id')
            ->toArray();

        foreach ($products as $product) {
            if (in_array($product->id, $data['product_ids'])) {
                $data[$product->id . '_price'] = $product->priceForUser($this->record, false);
                $data[$product->id . '_discount_price'] = $product->discountPriceForUser($this->record, false);
            }
        }

        return parent::mutateFormDataBeforeFill($data); // TODO: Change the autogenerated stub
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $products = Product::all();

        foreach ($products as $product) {
            if (in_array($product->id, $data['product_ids'])) {
                $price = $data[$product->id . '_price'];
                $discount_price = $data[$product->id . '_discount_price'];

                DB::table('dashed__product_user')
                    ->updateOrInsert(
                        ['product_id' => $product->id, 'user_id' => $this->record->id],
                        ['price' => $price, 'discount_price' => $discount_price]
                    );

            }
        }

        DB::table('dashed__product_user')
            ->where('user_id', $this->record->id)
            ->whereNotIn('product_id', $data['product_ids'])
            ->delete();

        $data = [];

        return parent::mutateFormDataBeforeSave($data); // TODO: Change the autogenerated stub
    }
}
