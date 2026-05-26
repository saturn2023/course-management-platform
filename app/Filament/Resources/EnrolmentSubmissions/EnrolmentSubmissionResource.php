<?php

namespace App\Filament\Resources\EnrolmentSubmissions;

use App\Filament\Resources\EnrolmentSubmissions\Pages\ListEnrolmentSubmissions;
use App\Filament\Resources\EnrolmentSubmissions\Pages\ViewEnrolmentSubmission;
use App\Filament\Resources\EnrolmentSubmissions\Schemas\EnrolmentSubmissionForm;
use App\Filament\Resources\EnrolmentSubmissions\Schemas\EnrolmentSubmissionInfolist;
use App\Filament\Resources\EnrolmentSubmissions\Tables\EnrolmentSubmissionsTable;
use App\Models\EnrolmentSubmission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class EnrolmentSubmissionResource extends Resource
{
    protected static ?string $model = EnrolmentSubmission::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'id';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return EnrolmentSubmissionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EnrolmentSubmissionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EnrolmentSubmissionsTable::configure($table);
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
            'index' => ListEnrolmentSubmissions::route('/'),
            'view' => ViewEnrolmentSubmission::route('/{record}'),
        ];
    }
}