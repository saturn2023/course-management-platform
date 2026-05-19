<?php

namespace App\Filament\Resources\IntegrationLogs\Pages;

use App\Filament\Resources\IntegrationLogs\IntegrationLogResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListIntegrationLogs extends ListRecords
{
    protected static string $resource = IntegrationLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
