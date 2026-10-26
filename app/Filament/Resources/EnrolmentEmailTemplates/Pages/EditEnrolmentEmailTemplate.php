<?php

namespace App\Filament\Resources\EnrolmentEmailTemplates\Pages;

use App\Filament\Resources\EnrolmentEmailTemplates\Actions\SendTestEmailAction;
use App\Filament\Resources\EnrolmentEmailTemplates\EnrolmentEmailTemplateResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEnrolmentEmailTemplate extends EditRecord
{
    protected static string $resource = EnrolmentEmailTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SendTestEmailAction::make(),
            DeleteAction::make(),
        ];
    }
}
