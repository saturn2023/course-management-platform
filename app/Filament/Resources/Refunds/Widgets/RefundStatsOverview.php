<?php

namespace App\Filament\Resources\Refunds\Widgets;

use App\Models\Refund;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Shown on the Refunds list page only (registered as a header widget there),
 * so the shared admin dashboard is not changed.
 */
class RefundStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $balanceAffecting = Refund::query()->affectsBalance();

        return [
            Stat::make(
                'Total refunds amount',
                'A$' . number_format((float) $balanceAffecting->clone()->sum('amount'), 2)
            )->description('Recorded, pending and processed refunds'),

            Stat::make(
                'Number of refunds',
                (string) Refund::query()->count()
            )->description('All refund records'),
        ];
    }
}
