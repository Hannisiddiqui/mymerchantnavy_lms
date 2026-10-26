<?php

namespace App\Filament\Support;

use App\Enums\ContentType;
use App\Enums\TestKind;
use App\Enums\VideoProvider;
use App\Models\Course;
use App\Models\Test;
use App\Services\Courses\CourseOutline;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;

/**
 * The form for each chapter content type (opened in a slide-over from the course builder).
 * To support a new type: add a case to App\Enums\ContentType, a branch here, and one in
 * App\Services\Courses\CourseOutline::saveMaterial().
 */
class ContentForm
{
    /** Icon and label used by the builder for each type. */
    public const ICONS = [
        'video' => 'heroicon-o-play-circle',
        'live_class' => 'heroicon-o-signal',
        'pdf' => 'heroicon-o-document-text',
        'note' => 'heroicon-o-document',
        'assignment' => 'heroicon-o-clipboard-document-check',
        'practice_test' => 'heroicon-o-bolt',
    ];

    public static function schema(ContentType $type, int $courseId): array
    {
        $title = TextInput::make('title')->label('Title shown to students')->required()->maxLength(150)->autofocus();

        return [$title, ...match ($type) {
            ContentType::Video => [
                Select::make('provider')->label('Hosted on')
                    ->options(collect(VideoProvider::cases())->mapWithKeys(fn ($p) => [$p->value => $p->name]))->default('youtube')->required(),
                TextInput::make('provider_ref')->label('Video link or ID')->required(),
                TextInput::make('duration_minutes')->label('Duration (minutes)')->numeric()->minValue(0),
            ],
            ContentType::LiveClass => [
                DateTimePicker::make('starts_at')->label('Starts at')->required(),
                TextInput::make('duration_minutes')->label('Duration (minutes)')->numeric()->default(60)->required(),
                Select::make('provider')->label('Streaming provider (optional)')->options(['agora' => 'Agora', 'youtube' => 'YouTube Live'])
                    ->helperText('Live streaming is connected in a later milestone. Students are reminded before the class starts.'),
            ],
            ContentType::Pdf => [
                FileUpload::make('file')->label('PDF file')->required()->acceptedFileTypes(['application/pdf'])
                    ->disk('local')->directory('pdfs')->maxSize(51200)
                    ->helperText('Stored privately. Students see watermarked pages only and can never download the file.'),
            ],
            ContentType::Note => [
                RichEditor::make('body')->label('Notes')->required()
                    ->fileAttachmentsDisk('public')->fileAttachmentsDirectory('note-images')
                    ->helperText('Maths: write LaTeX between $ signs.'),
            ],
            ContentType::Assignment => [
                RichEditor::make('instructions')->required(),
                DateTimePicker::make('due_at')->label('Due date (optional)'),
            ],
            ContentType::PracticeTest => self::testPicker($courseId, TestKind::Practice, askTitle: false),
        }];
    }

    /**
     * "Use an existing test" or "Create a new test" with its own fields, for mock and practice tests.
     * Read the result with resolveTest(). A new practice test is named after the content title.
     */
    public static function testPicker(int $courseId, TestKind $kind, bool $askTitle = true): array
    {
        $noun = $kind === TestKind::Mock ? 'mock test' : 'practice test';
        $existing = fn () => $kind === TestKind::Mock
            ? app(CourseOutline::class)->unplacedMockTests(Course::findOrFail($courseId))
            : Test::where('course_id', $courseId)->where('kind', $kind)->orderBy('title')->pluck('title', 'id');
        $isNew = fn (Get $get) => $get('mode') === 'new';

        return [
            ToggleButtons::make('mode')->label('What do you want to do?')->inline()->grouped()->live()->required()
                ->options(['existing' => "Add an existing {$noun}", 'new' => "Create a new {$noun}"])
                ->icons(['existing' => 'heroicon-m-magnifying-glass', 'new' => 'heroicon-m-plus-circle'])
                ->default(fn () => $existing()->isEmpty() ? 'new' : 'existing'),

            Select::make('test_id')->label(ucfirst($noun))->searchable()->preload()
                ->options($existing)
                ->visible(fn (Get $get) => $get('mode') === 'existing')
                ->required(fn (Get $get) => $get('mode') === 'existing')
                ->helperText("Only {$noun}s that are not placed yet are listed."),

            Group::make([
                ...($askTitle ? [TextInput::make('new_title')->label(ucfirst($noun).' name')->required()->maxLength(150)->columnSpanFull()] : []),
                TextInput::make('new_duration_minutes')->label('Duration (minutes)')->numeric()->required()->default(30)->minValue(1),
                TextInput::make('new_marks_correct')->label('Marks for a correct answer')->numeric()->required()->default(2),
                TextInput::make('new_marks_wrong')->label('Marks deducted for a wrong answer')->numeric()->step(0.25)->required()->default(0.25),
            ])->columns(3)->visible($isNew),
        ];
    }

    /** The test chosen or described by testPicker(): finds the existing one, or creates the new one. */
    public static function resolveTest(array $data, int $courseId, TestKind $kind, ?string $fallbackTitle = null): Test
    {
        if (($data['mode'] ?? 'existing') === 'existing') {
            return Test::where('course_id', $courseId)->where('kind', $kind)->findOrFail($data['test_id']);
        }

        return Test::create([
            'course_id' => $courseId,
            'kind' => $kind,
            'title' => $data['new_title'] ?? $fallbackTitle ?? 'New test',
            'duration_minutes' => (int) $data['new_duration_minutes'],
            'marks_correct' => $data['new_marks_correct'],
            'marks_wrong' => $data['new_marks_wrong'],
            'is_published' => true,
        ]);
    }
}
