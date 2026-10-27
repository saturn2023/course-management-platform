<?php

namespace App\Filament\Resources\StudentDocuments\Pages;

use App\Filament\Resources\StudentDocuments\StudentDocumentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStudentDocument extends CreateRecord
{
    protected static string $resource = StudentDocumentResource::class;
}
