<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('student_id')
                    ->relationship('student', 'id')
                    ->default(null),
                TextInput::make('subtotal')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('total')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('status')
                    ->required()
                    ->default('pending'),
                TextInput::make('xero_status')
                    ->required()
                    ->default('pending'),
                TextInput::make('enrolment_status')
                    ->required()
                    ->default('pending'),
                TextInput::make('xero_invoice_id')
                    ->default(null),
                TextInput::make('xero_invoice_number')
                    ->default(null),
                Textarea::make('xero_error_message')
                    ->default(null)
                    ->columnSpanFull(),
            ]);
    }
}
