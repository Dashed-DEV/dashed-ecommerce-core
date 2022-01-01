<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Widgets\Revenue;

use Filament\Widgets\LineChartWidget;
use Illuminate\Support\Facades\Cache;
use Qubiqx\QcommerceCore\Models\User;
use Qubiqx\QcommerceEcommerceCore\Models\Order;

class DashboardFunLineChartStats extends LineChartWidget
{
    protected function getHeading(): string
    {
        return 'Fun stats';
    }

    protected function getData(): array
    {
        $statistics = Cache::remember('monthly-fun-data-line-chart-stats', 60 * 60, function () {
            $statistics = [];

            $monthDate = now()->subMonth();
            while ($monthDate < now()) {
                $statistics['newUsers'][] = User::where('created_at', '>=', $monthDate->copy()->startOfDay())->where('created_at', '<=', $monthDate->copy()->endOfDay())->count();
                $statistics['newOrders'][] = Order::where('created_at', '>=', $monthDate->copy()->startOfDay())->where('created_at', '<=', $monthDate->copy()->endOfDay())->isPaid()->count();
                $statistics['labels'][] = $monthDate->format('d-m-Y');
                $monthDate->addDay();
            }

            return $statistics;
        });

        return [
            'datasets' => [
                [
                    'label' => 'Nieuwe gebruikers',
                    'data' => $statistics['newUsers'],
                    'backgroundColor' => 'lightblue',
                    'borderColor' => "blue",
                    'fill' => 'start',
                ],
                [
                    'label' => 'Nieuwe bestellingen',
                    'data' => $statistics['newOrders'],
                    'backgroundColor' => 'yellow',
                    'borderColor' => "gold",
                    'fill' => 'start',
                ],
            ],
            'labels' => $statistics['labels'],
        ];
    }
}