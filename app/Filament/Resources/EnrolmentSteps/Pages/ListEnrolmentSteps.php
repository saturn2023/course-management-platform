<?php

namespace App\Filament\Resources\EnrolmentSteps\Pages;

use App\Filament\Resources\EnrolmentSteps\EnrolmentStepResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEnrolmentSteps extends ListRecords
{
    protected static string $resource = EnrolmentStepResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
