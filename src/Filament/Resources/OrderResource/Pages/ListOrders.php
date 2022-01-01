<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources\OrderResource\Pages;

use Illuminate\Support\HtmlString;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Support\Facades\Storage;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Actions\ButtonAction;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Placeholder;
use Illuminate\Database\Eloquent\Collection;
use Filament\Tables\Filters\MultiSelectFilter;
use Qubiqx\QcommerceEcommerceCore\Models\Order;
use Qubiqx\QcommerceEcommerceCore\Classes\Orders;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\OrderResource;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getTableBulkActions(): array
    {
        return [
            BulkAction::make('downloadInvoices')
                ->label('Download facturen')
                ->color('primary')
                ->action(fn (Collection $records) => function ($records) {
                    $this->notify('success', 'test');

                    return redirect('/test');
                    exit;

                    return Storage::download('/exports/invoices/exported-invoice.pdf');
                })
                ->deselectRecordsAfterCompletion(),
        ];
    }

    protected function getTableActions(): array
    {
        return array_merge(parent::getTableActions(), [
            ButtonAction::make('quickActions')
                ->label('Quick')
                ->color('primary')
                ->modalHeading('Snel bewerken')
                ->modalButton('Opslaan')
                ->form([
                    Section::make('Status')
                        ->schema([
                            Select::make('fulfillment_status')
                                ->label('Fulfillment status')
                                ->options(Orders::getFulfillmentStatusses())
                                ->required()
                                ->default(fn ($record) => $record->fulfillment_status)
                                ->hidden(fn ($record) => $record->credit_for_order_id),
                            Select::make('retour_status')
                                ->label('Retour status')
                                ->options(Orders::getReturnStatusses())
                                ->required()
                                ->default(fn ($record) => $record->retour_status)
                                ->hidden(fn ($record) => ! $record->credit_for_order_id),
                        ])
                        ->columns([
                            'default' => 1,
                            'lg' => 2,
                        ]),
                    Section::make('Informatie')
                        ->schema([
                            Placeholder::make('shippingAddress')
                                ->label('Verzendadres')
                                ->content(fn ($record) => new HtmlString(($record->company_name ? $record->company_name . '<br>' : '') . "$record->name<br>$record->street $record->house_nr<br>$record->city $record->zip_code<br>$record->country")),
                            Placeholder::make('shippingAddress')
                                ->label('Factuuradres')
                                ->content(fn ($record) => new HtmlString(($record->company_name ? $record->company_name . '<br>' : '') . "$record->name<br>$record->invoice_street $record->invoice_house_nr<br>$record->invoice_city $record->invoice_zip_code<br>$record->invoice_country")),
                        ])
                        ->columns([
                            'default' => 1,
                            'lg' => 2,
                        ]),
                ])
                ->action(function (Order $record, array $data): void {
                    if (isset($data['fulfillment_status'])) {
                        $record->fulfillment_status = $data['fulfillment_status'];
                    }
                    if (isset($data['retour_status'])) {
                        $record->retour_status = $data['retour_status'];
                    }
                    $record->save();
                }),
        ]);
    }

    protected function getTableFilters(): array
    {
        $orderOrigins = [];
        foreach (Order::distinct('order_origin')->pluck('order_origin')->unique() as $orderOrigin) {
            $orderOrigins[$orderOrigin] = ucfirst($orderOrigin);
        }

        return [
            MultiSelectFilter::make('status')
                ->options([
                    'paid' => 'Betaald',
                    'partially_paid' => 'Gedeeltelijk betaald',
                    'waiting_for_confirmation' => 'Wachten op bevestiging',
                    'pending' => 'Lopende aankoop',
                    'cancelled' => 'Geannuleerd',
                    'return' => 'Retour',
                ]),
//            MultiSelectFilter::make('payment_method')
//                ->options(OrderPayment::whereNotNull('payment_method')->distinct('payment_method')->pluck('payment_method')->unique()),
            MultiSelectFilter::make('fulfillment_status')
                ->options(Orders::getFulfillmentStatusses()),
            MultiSelectFilter::make('retour_status')
                ->options(Orders::getReturnStatusses()),
            MultiSelectFilter::make('order_origin')
                ->options($orderOrigins),
            Filter::make('start_date')
                ->form([
                    DatePicker::make('start_date')
                        ->label('Startdatum'),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when(
                            $data['start_date'],
                            fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                        );
                }),
            Filter::make('end_date')
                ->form([
                    DatePicker::make('end_date')
                        ->label('Einddatum'),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when(
                            $data['end_date'],
                            fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                        );
                }),
        ];
    }

    protected function getTableFiltersFormColumns(): int|array
    {
        return 4;
    }
}