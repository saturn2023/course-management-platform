<?php

namespace App\Filament\Resources\EnrolmentSubmissions\Pages;

use App\Filament\Resources\EnrolmentSubmissions\EnrolmentSubmissionResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditEnrolmentSubmission extends EditRecord
{
    protected static string $resource = EnrolmentSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
