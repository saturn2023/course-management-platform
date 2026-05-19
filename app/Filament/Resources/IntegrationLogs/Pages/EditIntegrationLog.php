<?php

namespace App\Filament\Resources\IntegrationLogs\Pages;

use App\Filament\Resources\IntegrationLogs\IntegrationLogResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditIntegrationLog extends EditRecord
{
    protected static string $resource = IntegrationLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
