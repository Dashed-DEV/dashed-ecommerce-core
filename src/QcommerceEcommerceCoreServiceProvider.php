<?php

namespace Qubiqx\QcommerceEcommerceCore;

use Livewire\Livewire;
use Filament\PluginServiceProvider;
use Spatie\LaravelPackageTools\Package;
use Illuminate\Console\Scheduling\Schedule;
use Qubiqx\QcommerceEcommerceCore\Models\ProductCategory;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\OrderResource;
use Qubiqx\QcommerceEcommerceCore\Livewire\Orders\AddPaymentToOrder;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductResource;
use Qubiqx\QcommerceEcommerceCore\Classes\ProductCategoryRouteHandler;
use Qubiqx\QcommerceEcommerceCore\Filament\Pages\Exports\ExportOrdersPage;
use Qubiqx\QcommerceEcommerceCore\Filament\Pages\Settings\VATSettingsPage;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\DiscountCodeResource;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ShippingZoneResource;
use Qubiqx\QcommerceEcommerceCore\Livewire\Orders\ChangeOrderRetourStatus;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\PaymentMethodResource;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductFilterResource;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ShippingClassResource;
use Qubiqx\QcommerceEcommerceCore\Filament\Pages\Exports\ExportInvoicesPage;
use Qubiqx\QcommerceEcommerceCore\Filament\Pages\Exports\ExportProductsPage;
use Qubiqx\QcommerceEcommerceCore\Filament\Pages\Settings\OrderSettingsPage;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ShippingMethodResource;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductCategoryResource;
use Qubiqx\QcommerceEcommerceCore\Filament\Widgets\Revenue\DailyRevenueStats;
use Qubiqx\QcommerceEcommerceCore\Filament\Pages\Settings\InvoiceSettingsPage;
use Qubiqx\QcommerceEcommerceCore\Filament\Pages\Settings\ProductSettingsPage;
use Qubiqx\QcommerceEcommerceCore\Filament\Widgets\Revenue\YearlyRevenueStats;
use Qubiqx\QcommerceEcommerceCore\Filament\Pages\Settings\CheckoutSettingsPage;
use Qubiqx\QcommerceEcommerceCore\Filament\Widgets\Revenue\MonthlyRevenueStats;
use Qubiqx\QcommerceEcommerceCore\Livewire\Orders\ChangeOrderFulfillmentStatus;
use Qubiqx\QcommerceEcommerceCore\Livewire\Orders\SendOrderConfirmationToEmail;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductFilterOptionResource;
use Qubiqx\QcommerceEcommerceCore\Filament\Pages\Statistics\ProductStatisticsPage;
use Qubiqx\QcommerceEcommerceCore\Filament\Pages\Statistics\RevenueStatisticsPage;
use Qubiqx\QcommerceEcommerceCore\Filament\Pages\Statistics\DiscountStatisticsPage;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductCharacteristicResource;
use Qubiqx\QcommerceEcommerceCore\Filament\Widgets\Revenue\DailyReturnRevenueStats;
use Qubiqx\QcommerceEcommerceCore\Filament\Widgets\Revenue\YearlyReturnRevenueStats;
use Qubiqx\QcommerceEcommerceCore\Filament\Widgets\Revenue\MonthlyReturnRevenueStats;
use Qubiqx\QcommerceEcommerceCore\Filament\Widgets\Revenue\DashboardFunLineChartStats;
use Qubiqx\QcommerceEcommerceCore\Filament\Widgets\Revenue\MonthlyRevenueAndReturnLineChartStats;

class QcommerceEcommerceCoreServiceProvider extends PluginServiceProvider
{
    public static string $name = 'qcommerce-core';

    public function bootingPackage()
    {
        $this->app->booted(function () {
            $schedule = app(Schedule::class);
//            $schedule->command(CreateSitemap::class)->daily();
        });

        Livewire::component('change-order-fulfillment-status', ChangeOrderFulfillmentStatus::class);
        Livewire::component('change-order-retour-status', ChangeOrderRetourStatus::class);
        Livewire::component('add-payment-to-order', AddPaymentToOrder::class);
        Livewire::component('send-order-confirmation-to-email', SendOrderConfirmationToEmail::class);
    }

    public function configurePackage(Package $package): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        cms()->builder(
            'routeModels',
            array_merge(cms()->builder('routeModels'), [
                'productCategory' => [
                    'name' => 'Product categorie',
                    'pluralName' => 'Product categorieën',
                    'class' => ProductCategory::class,
                    'nameField' => 'name',
                    'routeHandler' => ProductCategoryRouteHandler::class,
                ],
            ])
        );

        cms()->builder(
            'settingPages',
            array_merge(cms()->builder('settingPages'), [
                'invoicing' => [
                    'name' => 'Facturatie instellingen',
                    'description' => 'Instellingen voor de facturatie',
                    'icon' => 'document-report',
                    'page' => InvoiceSettingsPage::class,
                ],
                'order' => [
                    'name' => 'Bestellingen',
                    'description' => 'Instellingen voor de bestellingen',
                    'icon' => 'cash',
                    'page' => OrderSettingsPage::class,
                ],
                'paymentMethods' => [
                    'name' => 'Betaalmethodes',
                    'description' => 'Stel handmatige betaalmethodes in',
                    'icon' => 'credit-card',
                    'page' => PaymentMethodResource::class,
                ],
                'vat' => [
                    'name' => 'BTW instellingen',
                    'description' => 'Beheren hoe je winkel belastingen in rekening brengt',
                    'icon' => 'receipt-tax',
                    'page' => VATSettingsPage::class,
                ],
                'product' => [
                    'name' => 'Product instellingen',
                    'description' => 'Beheren instellingen over je producten',
                    'icon' => 'shopping-bag',
                    'page' => ProductSettingsPage::class,
                ],
                'checkout' => [
                    'name' => 'Afreken instellingen',
                    'description' => 'Je online betaalprocess aanpassen',
                    'icon' => 'shopping-cart',
                    'page' => CheckoutSettingsPage::class,
                ],
                'shippingClass' => [
                    'name' => 'Verzendklasses',
                    'description' => 'Is een product breekbaar of veel groter? Reken een meerprijs',
                    'icon' => 'truck',
                    'page' => ShippingClassResource::class,
                ],
                'shippingZone' => [
                    'name' => 'Verzendzones',
                    'description' => 'Bepaal waar je allemaal naartoe verstuurd',
                    'icon' => 'truck',
                    'page' => ShippingZoneResource::class,
                ],
                'shippingMethod' => [
                    'name' => 'Verzendmethodes',
                    'description' => 'Maak verzendmethodes aan',
                    'icon' => 'truck',
                    'page' => ShippingMethodResource::class,
                ],
            ])
        );

        $package
            ->name('qcommerce-ecommerce-core')
            ->hasConfigFile([
//                'filament',
//                'filament-spatie-laravel-translatable-plugin',
//                'filesystems',
//                'laravellocalization',
//                'media-library',
//                'qcommerce-core',
            ])
            ->hasRoutes([
                'frontend',
            ])
            ->hasViews()
            ->hasAssets()
            ->hasCommands([
//                CreateSitemap::class,
            ]);
    }

    protected function getStyles(): array
    {
        return array_merge(parent::getStyles(), [
            'qcommerce-ecommerce-core' => str_replace('/vendor/qubiqx/qcommerce-ecommerce-core/src', '', str_replace('/packages/qubiqx/qcommerce-ecommerce-core/src', '', __DIR__)) . '/vendor/qubiqx/qcommerce-ecommerce-core/resources/dist/css/qcommerce-ecommerce-core.css',
        ]);
    }

    protected function getPages(): array
    {
        return array_merge(parent::getPages(), [
            InvoiceSettingsPage::class,
            OrderSettingsPage::class,
            CheckoutSettingsPage::class,
            ProductSettingsPage::class,
            VATSettingsPage::class,
            ExportInvoicesPage::class,
            ExportOrdersPage::class,
            ExportProductsPage::class,
            RevenueStatisticsPage::class,
            ProductStatisticsPage::class,
            DiscountStatisticsPage::class,
        ]);
    }

    protected function getResources(): array
    {
        return array_merge(parent::getResources(), [
            PaymentMethodResource::class,
            ShippingClassResource::class,
            ShippingZoneResource::class,
            ShippingMethodResource::class,
            DiscountCodeResource::class,
            ProductResource::class,
            ProductCategoryResource::class,
            ProductFilterResource::class,
            ProductFilterOptionResource::class,
            ProductCharacteristicResource::class,
            OrderResource::class,
        ]);
    }

    protected function getWidgets(): array
    {
        return array_merge(parent::getWidgets(), [
            MonthlyRevenueAndReturnLineChartStats::class,
            DashboardFunLineChartStats::class,
            DailyRevenueStats::class,
            MonthlyRevenueStats::class,
            YearlyRevenueStats::class,
            DailyReturnRevenueStats::class,
            MonthlyReturnRevenueStats::class,
            YearlyReturnRevenueStats::class,
        ]);
    }
}
