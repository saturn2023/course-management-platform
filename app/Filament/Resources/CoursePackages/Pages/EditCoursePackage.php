<?php

namespace App\Filament\Resources\CoursePackages\Pages;

use App\Filament\Resources\CoursePackages\CoursePackageResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCoursePackage extends EditRecord
{
    protected static string $resource = CoursePackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
