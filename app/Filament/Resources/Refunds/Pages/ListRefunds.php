<?php

namespace App\Filament\Resources\Refunds\Pages;

use App\Filament\Resources\Refunds\RefundResource;
use App\Filament\Resources\Refunds\Widgets\RefundStatsOverview;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRefunds extends ListRecords
{
    protected static string $resource = RefundResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * Refund totals are shown above the Refunds list only, so the shared
     * admin dashboard is left untouched.
     */
    protected function getHeaderWidgets(): array
    {
        return [
            RefundStatsOverview::class,
        ];
    }
}
