<?php

namespace App\Filament\Resources\EnrolmentEmailTemplates\Pages;

use App\Filament\Resources\EnrolmentEmailTemplates\EnrolmentEmailTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEnrolmentEmailTemplates extends ListRecords
{
    protected static string $resource = EnrolmentEmailTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
