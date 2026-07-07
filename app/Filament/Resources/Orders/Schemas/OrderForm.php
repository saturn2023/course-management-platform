<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Course;
use App\Models\Student;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Hidden;
class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('student_id')
    ->default(null),

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
                            ->relationship(
                                name: 'student',
                                titleAttribute: 'email',
                            )
                            ->getOptionLabelFromRecordUsing(
                                fn (Student $record): string =>
                                    trim($record->first_name . ' ' . $record->last_name)
                                    . ' — '
                                    . $record->email
                            )
                            ->searchable([
                                'first_name',
                                'last_name',
                                'email',
                            ])
                            ->preload()
                            ->required(),
                    ])
                    ->columnSpanFull()
                    ->defaultItems(1),

                Select::make('status')
                    ->label('Order status')
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                        'cancelled' => 'Cancelled',
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
                    ->defaultItems(1)
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false),

                Section::make('Processing status')
                    ->description('Updated automatically by background jobs. Not manually editable.')
                    ->schema([
                        TextEntry::make('xero_status')
                            ->label('Xero status')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                'pending' => 'Pending',
                                'processing' => 'Processing',
                                'success' => 'Success',
                                'failed' => 'Failed',
                                default => (string) $state,
                            })
                            ->color(fn (?string $state): string => match ($state) {
                                'success' => 'success',
                                'processing' => 'info',
                                'failed' => 'danger',
                                default => 'warning',
                            }),

                        TextEntry::make('enrolment_status')
                            ->label('Enrolment status')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                'pending' => 'Pending',
                                'processing' => 'Processing',
                                'link_created' => 'Link created',
                                'link_sent' => 'Link sent',
                                'failed' => 'Failed',
                                default => (string) $state,
                            })
                            ->color(fn (?string $state): string => match ($state) {
                                'link_sent' => 'success',
                                'processing', 'link_created' => 'info',
                                'failed' => 'danger',
                                default => 'warning',
                            }),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

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