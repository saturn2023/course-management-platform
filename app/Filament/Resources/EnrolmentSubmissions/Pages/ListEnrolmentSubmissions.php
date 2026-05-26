<?php

namespace App\Filament\Resources\EnrolmentSubmissions\Pages;

use App\Filament\Resources\EnrolmentSubmissions\EnrolmentSubmissionResource;
use Filament\Resources\Pages\ListRecords;

class ListEnrolmentSubmissions extends ListRecords
{
    protected static string $resource = EnrolmentSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}