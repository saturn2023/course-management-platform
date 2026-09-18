<?php

namespace App\Filament\Resources\EnrolmentSteps\Pages;

use App\Filament\Resources\EnrolmentSteps\EnrolmentStepResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEnrolmentStep extends EditRecord
{
    protected static string $resource = EnrolmentStepResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
