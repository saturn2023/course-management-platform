<?php

namespace App\Filament\Resources\EnrolmentEmailTemplates;

use App\Filament\Resources\EnrolmentEmailTemplates\Pages\CreateEnrolmentEmailTemplate;
use App\Filament\Resources\EnrolmentEmailTemplates\Pages\EditEnrolmentEmailTemplate;
use App\Filament\Resources\EnrolmentEmailTemplates\Pages\ListEnrolmentEmailTemplates;
use App\Filament\Resources\EnrolmentEmailTemplates\Schemas\EnrolmentEmailTemplateForm;
use App\Filament\Resources\EnrolmentEmailTemplates\Tables\EnrolmentEmailTemplatesTable;
use App\Models\EnrolmentEmailTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class EnrolmentEmailTemplateResource extends Resource
{
    protected static ?string $model = EnrolmentEmailTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Enrolment Emails';

    protected static ?string $modelLabel = 'Enrolment Email';

    public static function form(Schema $schema): Schema
    {
        return EnrolmentEmailTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EnrolmentEmailTemplatesTable::configure($table);
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
            'index' => ListEnrolmentEmailTemplates::route('/'),
            'create' => CreateEnrolmentEmailTemplate::route('/create'),
            'edit' => EditEnrolmentEmailTemplate::route('/{record}/edit'),
        ];
    }
}
