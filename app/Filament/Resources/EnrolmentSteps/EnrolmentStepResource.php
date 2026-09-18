<?php

namespace App\Filament\Resources\EnrolmentSteps;

use App\Filament\Resources\EnrolmentSteps\Pages\CreateEnrolmentStep;
use App\Filament\Resources\EnrolmentSteps\Pages\EditEnrolmentStep;
use App\Filament\Resources\EnrolmentSteps\Pages\ListEnrolmentSteps;
use App\Filament\Resources\EnrolmentSteps\Schemas\EnrolmentStepForm;
use App\Filament\Resources\EnrolmentSteps\Tables\EnrolmentStepsTable;
use App\Models\EnrolmentStep;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class EnrolmentStepResource extends Resource
{
    protected static ?string $model = EnrolmentStep::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $navigationLabel = 'Enrolment Steps';

    public static function form(Schema $schema): Schema
    {
        return EnrolmentStepForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EnrolmentStepsTable::configure($table);
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
            'index' => ListEnrolmentSteps::route('/'),
            'create' => CreateEnrolmentStep::route('/create'),
            'edit' => EditEnrolmentStep::route('/{record}/edit'),
        ];
    }
}
