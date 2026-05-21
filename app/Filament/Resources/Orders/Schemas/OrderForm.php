<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Course;
use Filament\Forms\Components\Repeater;
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
                    ->label('Student')
                    ->relationship('student', 'id')
                    ->required(),

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

                Repeater::make('items')
                    ->label('Order Items')
                    ->relationship('items')
                    ->schema([
                        Select::make('course_id')
                            ->label('Course')
                            ->options(Course::query()->pluck('title', 'id'))
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                $course = Course::find($state);

                                if (! $course) {
                                    return;
                                }

                                $set('name', $course->title);
                                $set('unit_price', $course->price);
                                $set('quantity', 1);
                                $set('total', $course->price);
                            }),

                        TextInput::make('name')
                            ->label('Item name')
                            ->required(),

                        TextInput::make('quantity')
                            ->numeric()
                            ->required()
                            ->default(1)
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                $quantity = (float) ($state ?? 1);
                                $unitPrice = (float) ($get('unit_price') ?? 0);

                                $set('total', $quantity * $unitPrice);
                            }),

                        TextInput::make('unit_price')
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                $unitPrice = (float) ($state ?? 0);
                                $quantity = (float) ($get('quantity') ?? 1);

                                $set('total', $quantity * $unitPrice);
                            }),

                        TextInput::make('total')
                            ->numeric()
                            ->required()
                            ->default(0),
                    ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->defaultItems(1),

                TextInput::make('xero_invoice_id')
                    ->default(null)
                    ->disabled(),

                TextInput::make('xero_invoice_number')
                    ->default(null)
                    ->disabled(),

                Textarea::make('xero_error_message')
                    ->default(null)
                    ->disabled()
                    ->columnSpanFull(),
            ]);
    }
}