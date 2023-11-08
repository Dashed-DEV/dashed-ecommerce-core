<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\Statistics;

use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\OrderPayment;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\PaymentMethod;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\ProductCards;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\ProductChart;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\ProductTable;

class ProductStatisticsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';
    protected static ?string $navigationLabel = 'Product statistieken';
    protected static ?string $navigationGroup = 'Statistics';
    protected static ?string $title = 'Product statistieken';
    protected static ?int $navigationSort = 100000;

    protected static string $view = 'dashed-ecommerce-core::statistics.pages.product-statistics';

    public $search;
    public $startDate;
    public $endDate;
    public $graphData;

    public function mount(): void
    {
        $this->form->fill([
            'startDate' => now()->subMonth(),
            'endDate' => now(),
        ]);

        $this->getStatisticsProperty();
    }

    public function updated()
    {
        $this->getStatisticsProperty();
    }

    public function getStatisticsProperty()
    {
        $beginDate = $this->startDate ? Carbon::parse($this->startDate) : now()->subMonth();
        $endDate = $this->endDate ? Carbon::parse($this->endDate) : now()->addDay();

        $search = $this->search;
        $products = Product::notParentProduct()
            ->whereRaw('LOWER(name) like ?', '%' . strtolower($search) . '%')
            ->latest()
            ->get();

        $orderIds = Order::isPaid()
            ->where('created_at', '>=', $beginDate)
            ->where('created_at', '<=', $endDate)
            ->pluck('id');

        $orderProducts = OrderProduct::whereIn('order_id', $orderIds)->get();

        $totalQuantitySold = 0;
        $totalAmountSold = 0;
        $averageCostPerProduct = 0;

        foreach ($products as $product) {
            $product->quantitySold = $orderProducts->where('product_id', $product->id)->sum('quantity');
            $product->amountSold = $orderProducts->where('product_id', $product->id)->sum('price');
            $totalQuantitySold += $product->quantitySold;
            $totalAmountSold += $product->amountSold;
        }

        if ($totalQuantitySold) {
            $averageCostPerProduct = $totalAmountSold / $totalQuantitySold;
        }

        $statistics = [
            'totalQuantitySold' => $totalQuantitySold,
            'totalAmountSold' => CurrencyHelper::formatPrice($totalAmountSold),
            'averageCostPerProduct' => CurrencyHelper::formatPrice($averageCostPerProduct),
        ];

        $graph = [];

        $graphBeginDate = $beginDate->copy();
        while ($graphBeginDate < $endDate) {
            $graph['data'][] = OrderProduct::whereIn('id', $orderProducts->pluck('id'))->whereIn('product_id', $products->pluck('id'))->where('created_at', '>=', $graphBeginDate->copy()->startOfDay())->where('created_at', '<=', $graphBeginDate->copy()->endOfDay())->sum('quantity');
            $graph['labels'][] = $graphBeginDate->format('d-m-Y');
            $graphBeginDate->addDay();
        }

        $graphData = [
            'graph' => [
                'datasets' => [
                    [
                        'label' => 'Stats',
                        'data' => $graph['data'] ?? [],
                        'backgroundColor' => 'orange',
                        'borderColor' => "orange",
                        'fill' => 'start',
                    ],
                ],
                'labels' => $graph['labels'] ?? [],
            ],
            'filters' => [
                'search' => $search,
                'beginDate' => $beginDate,
                'endDate' => $endDate,
            ],
            'data' => $statistics,
            'products' => $products,
        ];

        $this->dispatch('updateGraphData', $graphData);
        $this->graphData = $graphData;
    }

    protected function getFormSchema(): array
    {
        $paymentMethods = [];
        foreach (PaymentMethod::get() as $paymentMethod) {
            $paymentMethods[$paymentMethod->id] = $paymentMethod->name;
        }

        foreach (OrderPayment::whereNotNull('payment_method')->distinct('payment_method')->pluck('payment_method')->unique() as $paymentMethod) {
            $paymentMethods[$paymentMethod] = $paymentMethod;
        }

        $orderOrigins = [];
        foreach (Order::whereNotNull('order_origin')->distinct('order_origin')->pluck('order_origin')->unique() as $orderOrigin) {
            $orderOrigins[$orderOrigin] = ucfirst($orderOrigin);
        }

        return [
            Section::make()
                ->schema([
                    DatePicker::make('startDate')
                        ->label('Start datum')
                        ->reactive(),
                    DatePicker::make('endDate')
                        ->label('Eind datum')
                        ->nullable()
                        ->after('startDate')
                        ->reactive(),
                    TextInput::make('search')
                        ->label('Zoekterm')
                        ->reactive(),
                ])
                ->columns([
                    'default' => 1,
                    'lg' => 3,
                ]),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            ProductChart::make(),
            ProductCards::make(),
            ProductTable::make(),
        ];
    }

    public function getWidgetData(): array
    {
        return [
            'graphData' => $this->graphData,
        ];
    }
}
