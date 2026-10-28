<?php

namespace App\Filament\Resources;

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Filament\Resources\QuestionReportResource\Pages\ListQuestionReports;
use App\Filament\Support\QuestionForm;
use App\Models\Question;
use App\Models\QuestionReport;
use App\Services\Tests\QuestionReportService;
use App\Services\Tests\QuestionService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Problems students reported on questions from their result page ("the answer key is wrong" …).
 * Open one, check the question, fix it in place if needed, then mark it resolved (or "no change needed").
 * Either way the student gets a note in the app with your reply.
 */
class QuestionReportResource extends Resource
{
    protected static ?string $model = QuestionReport::class;

    protected static ?string $modelLabel = 'question report';

    protected static ?string $navigationLabel = 'Question reports';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-flag';

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return ! (auth('admin')->user()?->isEditor() ?? false);
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth('admin')->user()?->isSuperAdmin() ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return auth('admin')->user()?->isSuperAdmin() ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    /** Number of open reports beside the menu item. */
    public static function getNavigationBadge(): ?string
    {
        $open = QuestionReport::where('status', ReportStatus::Open)->count();

        return $open ? (string) $open : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'question', 'test'])
                ->withCount(['sameQuestion as same_question_count' => fn (Builder $q) => $q->where('status', ReportStatus::Open)]))
            ->columns([
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('reason')->badge()->color('danger')->wrap(),
                TextColumn::make('question.body')->label('Question')->wrap()->extraAttributes(['class' => 'cb-math'])
                    ->formatStateUsing(fn ($state) => Str::limit(strip_tags((string) $state), 110))
                    ->description(fn (QuestionReport $record) => $record->test ? "Q #{$record->question_id} · {$record->test->title}" : "Q #{$record->question_id}"),
                TextColumn::make('details')->label('Student says')->wrap()->limit(140)->placeholder('No details')->searchable(),
                TextColumn::make('same_question_count')->label('Open reports')->badge()->color(fn ($state) => $state > 1 ? 'danger' : 'gray')
                    ->tooltip('Open reports on this same question, from all students')->sortable(),
                TextColumn::make('user.name')->label('Student')->searchable()->description(fn (QuestionReport $record) => $record->user?->loginId()),
                TextColumn::make('created_at')->label('Reported')->since()->sortable()
                    ->tooltip(fn (QuestionReport $record) => $record->created_at->format('d M Y, h:i A')),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options(ReportStatus::class)->default(ReportStatus::Open->value),
                SelectFilter::make('reason')->options(ReportReason::class),
                SelectFilter::make('test_id')->label('Test')->relationship('test', 'title')->searchable()->preload(),
            ])
            ->recordActions([
                Action::make('preview')->label('Open')->icon('heroicon-o-eye')->color('gray')
                    ->slideOver()->modalWidth('3xl')
                    ->modalHeading(fn (QuestionReport $record) => "Report on question #{$record->question_id}")
                    ->modalSubmitAction(false)->modalCancelActionLabel('Close')
                    ->modalContent(fn (QuestionReport $record) => static::preview($record)),
                static::fixAction(),
                ActionGroup::make([
                    static::closeAction('resolve', ReportStatus::Resolved, 'Mark resolved', 'heroicon-o-check-circle', 'success'),
                    static::closeAction('reject', ReportStatus::Rejected, 'No change needed', 'heroicon-o-x-circle', 'gray'),
                    Action::make('reopen')->label('Reopen')->icon('heroicon-o-arrow-path')->color('warning')
                        ->visible(fn (QuestionReport $record) => $record->status !== ReportStatus::Open)
                        ->action(fn (QuestionReport $record) => app(QuestionReportService::class)->reopen($record)),
                ]),
            ])
            ->toolbarActions([
                BulkAction::make('resolveSelected')
                    ->label('Bulk Resolve & Email')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->modalHeading('Resolve Selected Reports & Send Consolidated Notifications')
                    ->modalDescription('Each affected student receives in-app notifications and an optional consolidated Brevo email detailing their resolved questions.')
                    ->schema([
                        Textarea::make('admin_note')
                            ->label('Consolidated Instructor Feedback (Optional)')
                            ->rows(3)
                            ->placeholder('e.g. Questions have been reviewed by the academic faculty and answers updated in the question bank.'),
                        Toggle::make('send_email')
                            ->label('Send Consolidated Email Notifications (Brevo SMTP)')
                            ->helperText('Groups reports by student so each student receives only one clean summary email.')
                            ->default(true),
                    ])
                    ->action(function (Collection $records, array $data) {
                        $resolved = app(QuestionReportService::class)->bulkClose(
                            reports: $records,
                            status: ReportStatus::Resolved,
                            note: $data['admin_note'] ?? null,
                            admin: Auth::guard('admin')->user(),
                            sendEmail: (bool) ($data['send_email'] ?? true)
                        );
                        Notification::make()->title("{$resolved} report(s) marked resolved and notified")->success()->send();
                    })->deselectRecordsAfterCompletion(),
            ])
            ->emptyStateHeading('No reports here')
            ->emptyStateDescription('When students report a problem with a question, it appears here.')
            ->striped()
            ->paginated([15, 30, 50]);
    }

    public static function getPages(): array
    {
        return ['index' => ListQuestionReports::route('/')];
    }

    /** The question as students see it, with the correct answer, plus what the student wrote and the admin's reply. */
    private static function preview(QuestionReport $record)
    {
        $question = $record->question()->with('options')->first();
        $options = $question?->options->values() ?? collect();
        $chosenId = $record->attempt?->answers()->where('question_id', $record->question_id)->value('selected_option_id');
        $chosen = $chosenId ? $options->search(fn ($option) => $option->id === $chosenId) : false;

        return view('filament.question-report', [
            'report' => $record,
            'body' => $question?->body,
            'options' => $options->mapWithKeys(fn ($option, $i) => [$i + 1 => $option->body])->all(),
            'correct' => 1 + (int) $options->search(fn ($option) => $option->is_correct),
            'explanation' => $question?->explanation,
            'chosen' => $chosen === false ? null : $chosen + 1,
        ]);
    }

    /** Edit the question right here (same editor as the question bank), and close the report in the same step. */
    private static function fixAction(): Action
    {
        return Action::make('fix')->label('Fix question')->icon('heroicon-o-pencil-square')->color('primary')
            ->slideOver()->modalWidth('4xl')
            ->modalHeading(fn (QuestionReport $record) => "Fix question #{$record->question_id}")
            ->modalDescription(fn (QuestionReport $record) => $record->reason->getLabel().($record->details ? ': “'.$record->details.'”' : ''))
            ->visible(fn (QuestionReport $record) => $record->question !== null)
            ->fillForm(fn (QuestionReport $record) => app(QuestionService::class)->toForm($record->question) + [
                'close_report' => $record->status === ReportStatus::Open,
                'send_email' => true,
            ])
            ->schema(fn () => [
                ...QuestionForm::schema(),
                Section::make('Report Resolution')->schema([
                    Toggle::make('close_report')->label('Mark this report resolved and tell the student')->live(),
                    Textarea::make('admin_note')->label('Reply to the student (optional)')->rows(2)
                        ->placeholder('e.g. Thanks, the answer key was wrong; it is now (b).')
                        ->visible(fn ($get) => $get('close_report')),
                    Toggle::make('send_email')->label('Send Brevo Email Notification to Student')
                        ->default(true)
                        ->visible(fn ($get) => $get('close_report')),
                ]),
            ])
            ->action(function (QuestionReport $record, array $data) {
                app(QuestionService::class)->save(Arr::except($data, ['close_report', 'admin_note', 'send_email']), $record->question);
                if ($data['close_report'] ?? false) {
                    app(QuestionReportService::class)->close(
                        report: $record,
                        status: ReportStatus::Resolved,
                        note: $data['admin_note'] ?? null,
                        admin: Auth::guard('admin')->user(),
                        sendEmail: (bool) ($data['send_email'] ?? true)
                    );
                }
                Notification::make()->title(($data['close_report'] ?? false) ? 'Question saved and report resolved' : 'Question saved')->success()->send();
            });
    }

    private static function closeAction(string $name, ReportStatus $status, string $label, string $icon, string $color): Action
    {
        return Action::make($name)->label($label)->icon($icon)->color($color)
            ->visible(fn (QuestionReport $record) => $record->status === ReportStatus::Open)
            ->modalHeading($label)
            ->modalDescription('The student gets a note in the app with your reply.')
            ->schema([
                Textarea::make('admin_note')->label('Reply to the student (optional)')->rows(3)
                    ->placeholder($status === ReportStatus::Resolved ? 'e.g. Thanks, the typo in option C is fixed.' : 'e.g. The answer is correct: 15% of 80 is 12, see step 2 of the solution.'),
                Toggle::make('send_email')->label('Send Brevo Email Notification to Student')->default(true),
            ])
            ->action(function (QuestionReport $record, array $data) use ($status) {
                app(QuestionReportService::class)->close(
                    report: $record,
                    status: $status,
                    note: $data['admin_note'] ?? null,
                    admin: Auth::guard('admin')->user(),
                    sendEmail: (bool) ($data['send_email'] ?? true)
                );
                Notification::make()->title('Report closed and student notified')->success()->send();
            });
    }
}
