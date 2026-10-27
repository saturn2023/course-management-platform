<?php

namespace App\Filament\Resources\StudentDocuments\Pages;

use App\Filament\Resources\StudentDocuments\StudentDocumentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStudentDocument extends EditRecord
{
    protected static string $resource = StudentDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
