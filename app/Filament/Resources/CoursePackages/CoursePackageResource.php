<?php

namespace App\Filament\Resources\CoursePackages;

use App\Filament\Resources\CoursePackages\Pages\CreateCoursePackage;
use App\Filament\Resources\CoursePackages\Pages\EditCoursePackage;
use App\Filament\Resources\CoursePackages\Pages\ListCoursePackages;
use App\Filament\Resources\CoursePackages\Schemas\CoursePackageForm;
use App\Filament\Resources\CoursePackages\Tables\CoursePackagesTable;
use App\Models\CoursePackage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CoursePackageResource extends Resource
{
    protected static ?string $model = CoursePackage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $navigationLabel = 'Course Packages';

    public static function form(Schema $schema): Schema
    {
        return CoursePackageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CoursePackagesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCoursePackages::route('/'),
            'create' => CreateCoursePackage::route('/create'),
            'edit' => EditCoursePackage::route('/{record}/edit'),
        ];
    }
}
