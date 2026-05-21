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
                    ->label('Primary student')
                    ->relationship('student', 'id')
                    ->required(),

                TextInput::make('billing_first_name')
                    ->label('Billing first name'),

                TextInput::make('billing_last_name')
                    ->label('Billing last name'),

                TextInput::make('billing_company')
                    ->label('Company / Business name'),

                TextInput::make('billing_email')
                    ->label('Billing email')
                    ->email(),

                TextInput::make('billing_phone')
                    ->label('Billing phone'),

                TextInput::make('billing_address_1')
                    ->label('Street address 1'),

                TextInput::make('billing_address_2')
                    ->label('Street address 2'),

                TextInput::make('billing_city')
                    ->label('City'),

                TextInput::make('billing_postcode')
                    ->label('Postcode'),

                TextInput::make('billing_abn')
                    ->label('ABN'),

                Repeater::make('orderStudents')
                    ->label('Students for this order')
                    ->relationship('orderStudents')
                    ->schema([
                        Select::make('student_id')
                            ->label('Student')
                            ->relationship('student', 'id')
                            ->searchable()
                            ->required(),
                    ])
                    ->columnSpanFull()
                    ->defaultItems(1),

                TextInput::make('subtotal')
                    ->required()
                    ->numeric()
                    ->default(0.0),

                TextInput::make('total')
                    ->required()
                    ->numeric()
                    ->default(0.0),

                Select::make('status')
                    ->label('Order status')
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                        'cancelled' => 'Cancelled',
                    ])
                    ->required()
                    ->default('pending'),

                Select::make('xero_status')
                    ->label('Xero status')
                    ->options([
                        'pending' => 'Pending',
                        'processing' => 'Processing',
                        'success' => 'Success',
                        'failed' => 'Failed',
                        'skipped' => 'Skipped',
                    ])
                    ->required()
                    ->default('pending'),

                Select::make('enrolment_status')
                    ->label('Enrolment status')
                    ->options([
                        'pending' => 'Pending',
                        'processing' => 'Processing',
                        'link_created' => 'Link created',
                        'link_sent' => 'Link sent',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                        'skipped' => 'Skipped',
                    ])
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