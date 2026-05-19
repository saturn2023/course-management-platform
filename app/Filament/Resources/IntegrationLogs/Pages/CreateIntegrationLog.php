<?php

namespace App\Filament\Resources\IntegrationLogs\Pages;

use App\Filament\Resources\IntegrationLogs\IntegrationLogResource;
use Filament\Resources\Pages\CreateRecord;

class CreateIntegrationLog extends CreateRecord
{
    protected static string $resource = IntegrationLogResource::class;
}
