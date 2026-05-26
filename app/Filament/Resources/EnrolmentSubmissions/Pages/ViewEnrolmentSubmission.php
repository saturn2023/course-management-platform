<?php

namespace App\Filament\Resources\EnrolmentSubmissions\Pages;

use App\Filament\Resources\EnrolmentSubmissions\EnrolmentSubmissionResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewEnrolmentSubmission extends ViewRecord
{
    protected static string $resource = EnrolmentSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
