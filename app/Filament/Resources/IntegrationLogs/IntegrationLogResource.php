<?php

namespace App\Filament\Resources\IntegrationLogs;

use App\Filament\Resources\IntegrationLogs\Pages\CreateIntegrationLog;
use App\Filament\Resources\IntegrationLogs\Pages\EditIntegrationLog;
use App\Filament\Resources\IntegrationLogs\Pages\ListIntegrationLogs;
use App\Filament\Resources\IntegrationLogs\Schemas\IntegrationLogForm;
use App\Filament\Resources\IntegrationLogs\Tables\IntegrationLogsTable;
use App\Models\IntegrationLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class IntegrationLogResource extends Resource
{
    protected static ?string $model = IntegrationLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return IntegrationLogForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IntegrationLogsTable::configure($table);
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
            'index' => ListIntegrationLogs::route('/'),
            'create' => CreateIntegrationLog::route('/create'),
            'edit' => EditIntegrationLog::route('/{record}/edit'),
        ];
    }
}
