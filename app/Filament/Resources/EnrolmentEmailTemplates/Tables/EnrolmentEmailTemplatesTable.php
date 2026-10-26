<?php

namespace App\Filament\Resources\EnrolmentEmailTemplates\Tables;

use App\Filament\Resources\EnrolmentEmailTemplates\Actions\SendTestEmailAction;
use App\Models\EnrolmentEmailTemplate;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class EnrolmentEmailTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Template')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('course_id')
                    ->label('Course / plan')
                    ->formatStateUsing(fn (EnrolmentEmailTemplate $record) => $record->course_id.' / '.$record->plan_id)
                    ->searchable()
                    ->sortable(),

                IconColumn::make('is_published')
                    ->label('Published')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('attachments')
                    ->label('Attachments')
                    /*
                     * Counts files actually present on disk, not configured slots,
                     * so an attachment deleted from storage is visible here rather
                     * than only showing up as a warning in the log at send time.
                     */
                    ->state(function (EnrolmentEmailTemplate $record) {
                        $configured = count($record->attachmentRelativePaths());
                        $present = count($record->attachmentPaths());

                        return $present === $configured
                            ? (string) $configured
                            : $present.' of '.$configured.' (missing files)';
                    })
                    ->badge()
                    ->color(fn (string $state) => str_contains($state, 'missing') ? 'danger' : 'gray'),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                TernaryFilter::make('is_published')
                    ->label('Published'),
            ])
            ->recordActions([
                SendTestEmailAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
