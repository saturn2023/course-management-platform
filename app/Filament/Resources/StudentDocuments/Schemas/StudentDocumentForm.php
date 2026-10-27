<?php

namespace App\Filament\Resources\StudentDocuments\Schemas;

use App\Models\StudentDocument;
use Closure;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class StudentDocumentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('Title')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    // Slug follows the title on create only — after that it is
                    // the public URL and must not drift when the title is edited.
                    ->afterStateUpdated(function (string $operation, ?string $state, Set $set): void {
                        if ($operation === 'create') {
                            $set('slug', Str::slug((string) $state));
                        }
                    })
                    ->helperText('Shown as the link text on the Student Information page.'),

                TextInput::make('slug')
                    ->label('Slug')
                    ->required()
                    ->maxLength(255)
                    ->alphaDash()
                    ->unique(ignoreRecord: true)
                    ->helperText('Part of the download link (/student-information/documents/your-slug). Changing it breaks any existing links to this document, including ones already emailed or bookmarked.'),

                Section::make('File or link')
                    ->description('Upload a file or enter a link, not both. The download link stays the same when you replace the file.')
                    ->schema([
                        FileUpload::make('file_path')
                            ->label('File')
                            ->disk(StudentDocument::DISK)
                            ->directory(StudentDocument::DIRECTORY)
                            ->visibility('private')
                            ->storeFileNamesIn('original_name')
                            ->acceptedFileTypes([
                                'application/pdf',
                                'application/msword',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            ])
                            ->maxSize(20 * 1024)
                            ->downloadable()
                            ->helperText('PDF, DOC or DOCX, up to 20 MB.'),

                        /*
                         * Two rules work together to enforce exactly one.
                         *   requiredWithout('file_path') — implicit, so it
                         *     fires when both fields are empty.
                         *   The closure — non-implicit, so it only fires when
                         *     a URL was typed; that is the "both set" case.
                         */
                        TextInput::make('url')
                            ->label('Link')
                            ->url()
                            ->maxLength(2048)
                            ->requiredWithout('file_path')
                            ->validationMessages([
                                'required_without' => 'Upload a file or enter a link.',
                            ])
                            ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                if (filled($get('file_path')) && filled($value)) {
                                    $fail('Use either a file or a link, not both. Clear one of them.');
                                }
                            })
                            ->helperText('For link-only items such as Certificate Check. Opens in a new tab.'),
                    ])
                    ->columnSpanFull(),

                Toggle::make('is_active')
                    ->label('Show on Student Information page')
                    ->default(true),
            ]);
    }
}
