<?php

namespace App\Filament\Resources\CoursePackages\Pages;

use App\Filament\Resources\CoursePackages\CoursePackageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCoursePackages extends ListRecords
{
    protected static string $resource = CoursePackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
