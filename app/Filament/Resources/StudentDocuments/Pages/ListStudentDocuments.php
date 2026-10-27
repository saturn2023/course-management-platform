<?php

namespace App\Filament\Resources\StudentDocuments\Pages;

use App\Filament\Resources\StudentDocuments\StudentDocumentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStudentDocuments extends ListRecords
{
    protected static string $resource = StudentDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
