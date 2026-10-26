<?php

namespace App\Filament\Resources\EnrolmentEmailTemplates\Schemas;

use App\Models\Course;
use App\Models\EnrolmentEmailTemplate;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Str;

class EnrolmentEmailTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                /*
                 * Assigned before the first upload so attachments land in this
                 * template's own directory even while the record is still being
                 * created. Persisted, so editing keeps the same folder.
                 */
                Hidden::make('storage_key')
                    ->default(fn () => (string) Str::ulid())
                    ->dehydrated(),

                Section::make('Course')
                    ->description('Which course and plan this email belongs to. A published template here is also what puts this course/plan on the Enrolment v2 flow — without one it stays on the existing flow.')
                    ->schema([
                        Select::make('course_picker')
                            ->label('Pick a course')
                            ->options(fn () => Course::query()
                                ->whereNotNull('ams_enrolment_code')
                                ->whereNotNull('ams_plan_id')
                                ->orderBy('title')
                                ->get()
                                ->mapWithKeys(fn (Course $course) => [
                                    $course->ams_enrolment_code.':'.$course->ams_plan_id => trim($course->code.' '.$course->title),
                                ])
                                ->all())
                            ->searchable()
                            ->dehydrated(false)
                            ->helperText('Optional shortcut: fills the two IDs below. They stay editable for courses that are not in this system.')
                            ->live()
                            ->afterStateUpdated(function (?string $state, $set) {
                                if (! $state) {
                                    return;
                                }

                                [$courseId, $planId] = array_pad(explode(':', $state), 2, null);

                                $set('course_id', $courseId);
                                $set('plan_id', $planId);
                            }),

                        TextInput::make('course_id')
                            ->label('Course ID')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->required()
                            ->helperText('RTO Data course_id (e.g. 32).'),

                        /*
                         * Required here although the WordPress ACF group marks it
                         * optional: every template lookup matches on course_id AND
                         * plan_id, so a template saved without one can never be
                         * found. Deliberate divergence, recorded in the spec.
                         */
                        TextInput::make('plan_id')
                            ->label('Plan ID')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->required()
                            ->helperText('RTO Data plan_id (e.g. 38). Required: the lookup matches on both IDs.'),
                    ])
                    ->columns(2),

                Section::make('Email')
                    ->schema([
                        TextInput::make('name')
                            ->label('Template name')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Admin label only. Never sent to the learner.'),

                        Toggle::make('is_published')
                            ->label('Published')
                            ->default(false)
                            ->helperText('Only one published template is allowed per course/plan pair. Unpublished drafts are unlimited.'),

                        /*
                         * A plain textarea, not a rich editor: the WordPress field
                         * instruction is "Paste the full HTML template", and a rich
                         * editor would rewrite the pasted markup.
                         */
                        Textarea::make('email_body')
                            ->label('Email message body (HTML)')
                            ->required()
                            ->rows(18)
                            ->helperText('Paste the full HTML. Placeholders: {first_name} {last_name} {course_title} {site_url}. Names are inserted in UPPERCASE. {site_url} becomes this site address with no trailing slash, so write {site_url}/courses.')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Attachments')
                    ->description('Up to three. All optional — the email still sends with none. A file that is later deleted from disk is skipped at send time and logged.')
                    ->schema(
                        collect(range(1, EnrolmentEmailTemplate::ATTACHMENT_SLOTS))
                            ->map(fn (int $slot) => FileUpload::make("attachment_{$slot}_path")
                                ->label("Attachment {$slot}")
                                /*
                                 * Private disk: these are mail attachments and must
                                 * never be reachable by URL.
                                 */
                                ->disk(EnrolmentEmailTemplate::DISK)
                                ->visibility('private')
                                /*
                                 * Original filenames are what the learner sees in
                                 * their mail client, so they are preserved — which
                                 * is exactly why each template needs its own
                                 * directory. Two templates both uploading
                                 * "Learner Handbook.pdf" would otherwise collide.
                                 */
                                ->preserveFilenames()
                                ->directory(fn (Get $get) => EnrolmentEmailTemplate::directoryFor($get('storage_key')))
                                ->maxSize(10240)
                                ->helperText(match ($slot) {
                                    1 => 'e.g. Learner Handbook',
                                    2 => 'e.g. Learner Guide',
                                    3 => 'e.g. Schedule of Fees and Charges',
                                }))
                            ->all()
                    )
                    ->columns(1),
            ]);
    }
}
