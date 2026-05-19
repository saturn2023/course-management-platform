<?php

namespace App\Filament\Resources\IntegrationLogs\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class IntegrationLogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('order_id')
                    ->relationship('order', 'id')
                    ->default(null),
                TextInput::make('service')
                    ->required(),
                TextInput::make('action')
                    ->default(null),
                TextInput::make('status')
                    ->required()
                    ->default('pending'),
                Textarea::make('request_payload')
                    ->default(null)
                    ->columnSpanFull(),
                Textarea::make('response_payload')
                    ->default(null)
                    ->columnSpanFull(),
                Textarea::make('error_message')
                    ->default(null)
                    ->columnSpanFull(),
            ]);
    }
}
