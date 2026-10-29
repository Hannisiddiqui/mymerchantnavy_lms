<?php

namespace App\Filament\Resources;

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Filament\Resources\QuestionReportResource\Pages\ListQuestionReports;
use App\Filament\Support\QuestionForm;
use App\Models\ActivityLog;
use App\Models\Question;
use App\Models\QuestionReport;
use App\Models\Setting;
use App\Services\Tests\QuestionReportService;
use App\Services\Tests\QuestionService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
use Illuminate\Support\Facades\Mail;
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

    public static function canViewAny(): bool
    {
        $admin = auth('admin')->user();
        if (! $admin) {
            return false;
        }

        return $admin->isSuperAdmin() || $admin->hasPermission('manage_reports');
    }

    protected static ?int $navigationSort = 2;

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
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'question.options', 'test', 'attempt.answers'])
                ->withCount(['sameQuestion as same_question_count' => fn (Builder $q) => $q->where('status', ReportStatus::Open)]))
            ->columns([
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('reason')->badge()->color('danger')->wrap(),
                TextColumn::make('question.body')->label('Question')->wrap()->extraAttributes(['class' => 'cb-math'])
                    ->formatStateUsing(fn ($state) => Str::limit(strip_tags((string) $state), 110))
                    ->description(fn (QuestionReport $record) => $record->test ? "Q #{$record->question_id} · {$record->test->title}" : "Q #{$record->question_id}"),
                TextColumn::make('answer_status')
                    ->label('Answer Key & Student Pick')
                    ->state(function (QuestionReport $record) {
                        $q = $record->question;
                        if (! $q) return '—';
                        $options = $q->options ?? collect();
                        $correctOpt = $options->firstWhere('is_correct', true);
                        $correctIdx = $correctOpt ? $options->search(fn ($o) => $o->id === $correctOpt->id) : false;
                        $keyLetter = $correctIdx !== false ? chr(65 + $correctIdx) : '?';
                        $keyBody = $correctOpt ? Str::limit(strip_tags((string) $correctOpt->body), 45) : 'No key set';

                        $chosenId = $record->attempt?->answers()->where('question_id', $record->question_id)->value('selected_option_id');
                        $chosenOpt = $chosenId ? $options->firstWhere('id', $chosenId) : null;
                        $chosenIdx = $chosenOpt ? $options->search(fn ($o) => $o->id === $chosenOpt->id) : false;
                        $chosenLetter = $chosenIdx !== false ? chr(65 + $chosenIdx) : null;

                        $html = "<div style='font-size:12px;display:flex;flex-direction:column;gap:4px;'>";
                        $html .= "<div style='font-weight:600;color:#047857;background:#ecfdf5;padding:2px 6px;border-radius:4px;border:1px solid #a7f3d0;display:inline-block;'>✓ Key: ({$keyLetter}) " . e($keyBody) . "</div>";
                        
                        if ($chosenOpt) {
                            $isMatch = $chosenId === ($correctOpt?->id);
                            $textColor = $isMatch ? '#047857' : '#b91c1c';
                            $bgColor = $isMatch ? '#ecfdf5' : '#fef2f2';
                            $borderColor = $isMatch ? '#a7f3d0' : '#fecaca';
                            $chosenBody = Str::limit(strip_tags((string) $chosenOpt->body), 35);
                            $html .= "<div style='color:{$textColor};background:{$bgColor};padding:2px 6px;border-radius:4px;border:1px solid {$borderColor};display:inline-block;font-size:11px;'>👤 Student: ({$chosenLetter}) " . e($chosenBody) . "</div>";
                        }
                        $html .= "</div>";

                        return $html;
                    })
                    ->html()
                    ->wrap(),
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
                Action::make('emailChange')
                    ->label('Email Change')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->modalHeading(fn (QuestionReport $record) => "Email Question Change Notification (Q #{$record->question_id})")
                    ->modalDescription('Autofills the reported question, student details, and dispatches an audit email to the specified faculty/admin address directly from this dashboard.')
                    ->modalWidth('2xl')
                    ->schema([
                        TextInput::make('recipient_email')
                            ->label('Recipient Email Address')
                            ->default(fn () => Setting::get('audit_notification_emails', 'support.mymerchantnavy@gmail.com'))
                            ->required(),
                        TextInput::make('student_name')
                            ->label('Raised by Student')
                            ->default(fn (QuestionReport $record) => ($record->user?->name ?: 'Student #'.$record->user_id).' ('.($record->user?->email ?: 'No email').')')
                            ->disabled(),
                        TextInput::make('question_info')
                            ->label('Question Reference')
                            ->default(fn (QuestionReport $record) => "Question #{$record->question_id} · ".($record->test?->title ?? 'Question Bank'))
                            ->disabled(),
                        Textarea::make('question_body')
                            ->label('Reported Question Text')
                            ->default(fn (QuestionReport $record) => strip_tags((string) ($record->question?->body ?? '')))
                            ->rows(2)
                            ->disabled(),
                        Textarea::make('answer_details')
                            ->label('Options, Correct Key & Student Choice')
                            ->default(function (QuestionReport $record) {
                                $q = $record->question;
                                if (! $q) return 'No question data';
                                $options = $q->options ?? collect();
                                $chosenId = $record->attempt?->answers()->where('question_id', $record->question_id)->value('selected_option_id');
                                $lines = [];
                                foreach ($options as $idx => $opt) {
                                    $letter = chr(65 + $idx);
                                    $tags = [];
                                    if ($opt->is_correct) $tags[] = '✓ CORRECT KEY';
                                    if ($opt->id === $chosenId) $tags[] = '👤 STUDENT PICK';
                                    $tagStr = ! empty($tags) ? ' [' . implode(', ', $tags) . ']' : '';
                                    $lines[] = "({$letter}) " . strip_tags((string) $opt->body) . $tagStr;
                                }
                                return implode("\n", $lines);
                            })
                            ->rows(4)
                            ->disabled(),
                        TextInput::make('report_reason')
                            ->label('Student Report Reason')
                            ->default(fn (QuestionReport $record) => $record->reason->getLabel().($record->details ? " — \"{$record->details}\"" : ''))
                            ->disabled(),
                        Textarea::make('changes_made')
                            ->label('What Was Changed / Correction Details')
                            ->placeholder('e.g. Verified answer key; changed correct option from (b) to (c), fixed explanation formula.')
                            ->rows(3)
                            ->required(),
                        Toggle::make('mark_as_resolved')
                            ->label('Mark report as resolved in system')
                            ->default(true),
                    ])
                    ->action(function (QuestionReport $record, array $data) {
                        $admin = Auth::guard('admin')->user();
                        $adminEmail = $admin?->email ?? 'admin';
                        $recipient = $data['recipient_email'];
                        $changes = $data['changes_made'];
                        $studentInfo = ($record->user?->name ?: 'Student #'.$record->user_id).' ('.($record->user?->email ?: '—').')';
                        $qTitle = $record->test?->title ?? 'Question Bank';
                        $qBody = strip_tags((string) ($record->question?->body ?? ''));
                        $reason = $record->reason->getLabel().($record->details ? " — \"{$record->details}\"" : '');

                        $optionsFormatted = [];
                        $options = $record->question?->options ?? collect();
                        $chosenId = $record->attempt?->answers()->where('question_id', $record->question_id)->value('selected_option_id');
                        foreach ($options as $idx => $opt) {
                            $optionsFormatted[] = [
                                'letter' => chr(65 + $idx),
                                'body' => strip_tags((string) $opt->body),
                                'is_correct' => (bool) $opt->is_correct,
                                'was_student_pick' => ($opt->id === $chosenId),
                            ];
                        }

                        $viewData = [
                            'questionId' => $record->question_id,
                            'testTitle' => $qTitle,
                            'adminName' => $admin?->name ?? 'Academic Admin',
                            'adminEmail' => $adminEmail,
                            'adminRole' => method_exists($admin?->role, 'getLabel') ? $admin->role->getLabel() : ($admin?->role?->value ?? 'Administrator'),
                            'ipAddress' => request()->ip() ?? 'Unknown',
                            'studentInfo' => $studentInfo,
                            'reason' => $reason,
                            'details' => $record->details,
                            'qBody' => $qBody,
                            'options' => $optionsFormatted,
                            'changes' => $changes,
                            'timestamp' => now()->format('Y-m-d H:i:s'),
                            'dashboardUrl' => 'https://api.mymerchantnavy.com/admin/question-reports',
                        ];

                        $emails = array_filter(array_map('trim', preg_split('/[,\s;]+/', (string) $recipient)));
                        $validEmails = array_values(array_filter($emails, fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL)));
                        if (empty($validEmails)) {
                            $validEmails = ['support.mymerchantnavy@gmail.com'];
                        }

                        try {
                            Mail::send('emails.question-correction', $viewData, function ($msg) use ($validEmails, $record) {
                                $msg->to($validEmails)->subject("[Question Update] Question #{$record->question_id} Modified by Academic Admin");
                            });
                        } catch (\Throwable $e) {
                            report($e);
                            Notification::make()
                                ->title('Failed to Dispatch Email')
                                ->body('Error: ' . $e->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }

                        if ($data['mark_as_resolved'] ?? false) {
                            $record->update(['status' => ReportStatus::Resolved]);
                        }

                        ActivityLog::record(
                            event: 'question.report_change_emailed',
                            description: "Admin {$adminEmail} emailed change details for Question #{$record->question_id} to {$recipient}.",
                            subject: $record->question,
                            properties: [
                                'question_id' => $record->question_id,
                                'recipient' => $recipient,
                                'changes' => $changes,
                                'student_id' => $record->user_id,
                            ],
                            admin: $admin
                        );

                        Notification::make()
                            ->title('Question Change Notification Emailed')
                            ->body("Email dispatched to {$recipient}.")
                            ->success()
                            ->send();
                    }),
                ActionGroup::make([
                    static::closeAction('resolve', ReportStatus::Resolved, 'Mark resolved', 'heroicon-o-check-circle', 'success'),
                    static::closeAction('reject', ReportStatus::Rejected, 'No change needed', 'heroicon-o-x-circle', 'gray'),
                    Action::make('reopen')->label('Reopen')->icon('heroicon-o-arrow-path')->color('warning')
                        ->visible(fn (QuestionReport $record) => $record->status !== ReportStatus::Open)
                        ->action(fn (QuestionReport $record) => app(QuestionReportService::class)->reopen($record)),
                ]),
            ])
            ->headerActions([
                Action::make('bulkEmailAllOpen')
                    ->label('Bulk Email Open Reports')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->modalHeading('Bulk Email All Open Question Reports')
                    ->modalDescription('Autofetches all currently open student reports and emails a comprehensive change notification to the mentioned address.')
                    ->modalWidth('2xl')
                    ->schema([
                        TextInput::make('recipient_email')
                            ->label('Recipient Email Address')
                            ->default(fn () => Setting::get('audit_notification_emails', 'support.mymerchantnavy@gmail.com'))
                            ->required(),
                        Textarea::make('bulk_changes_summary')
                            ->label('Summary of Changes Made')
                            ->placeholder('e.g. Corrected questions across Open Reports; updated answer options, fixed typos, verified explanations with curriculum faculty.')
                            ->rows(3)
                            ->required(),
                        Toggle::make('mark_as_resolved')
                            ->label('Mark all open reports as resolved')
                            ->default(true),
                    ])
                    ->action(function (array $data) {
                        $records = QuestionReport::where('status', ReportStatus::Open)->with(['user', 'question.options', 'test'])->get();
                        if ($records->isEmpty()) {
                            Notification::make()->title('No open reports to email')->warning()->send();
                            return;
                        }
                        $admin = Auth::guard('admin')->user();
                        $adminEmail = $admin?->email ?? 'admin';
                        $recipient = $data['recipient_email'];
                        $summary = $data['bulk_changes_summary'];
                        $count = $records->count();

                        $questionsList = '';
                        foreach ($records as $index => $rep) {
                            $num = $index + 1;
                            $student = ($rep->user?->name ?: 'Student #'.$rep->user_id).' ('.($rep->user?->email ?: '—').')';
                            $qText = Str::limit(strip_tags((string) ($rep->question?->body ?? '')), 140);
                            $reason = $rep->reason instanceof ReportReason ? $rep->reason->getLabel() : ($rep->reason ?? 'Report');
                            $correctOpt = $rep->question?->options?->firstWhere('is_correct', true);
                            $keyText = $correctOpt ? strip_tags((string) $correctOpt->body) : 'Not set';
                            $questionsList .= "{$num}. Question #{$rep->question_id} ({$rep->test?->title})\n"
                                ."   Raised by: {$student} [Reason: {$reason}]\n"
                                ."   Question: {$qText}\n"
                                ."   Answer Key: {$keyText}\n\n";

                            if ($data['mark_as_resolved'] ?? false) {
                                $rep->update(['status' => ReportStatus::Resolved]);
                            }
                        }

                        $bulkData = [
                            'count' => $count,
                            'adminName' => $admin?->name ?? 'Academic Admin',
                            'adminEmail' => $adminEmail,
                            'ipAddress' => request()->ip() ?? 'Unknown',
                            'summary' => $summary,
                            'questionsList' => $questionsList,
                            'timestamp' => now()->format('Y-m-d H:i:s'),
                        ];

                        $emails = array_filter(array_map('trim', preg_split('/[,\s;]+/', (string) $recipient)));
                        $validEmails = array_values(array_filter($emails, fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL)));
                        if (empty($validEmails)) {
                            $validEmails = ['support.mymerchantnavy@gmail.com'];
                        }

                        try {
                            Mail::send('emails.bulk-question-correction', $bulkData, function ($msg) use ($validEmails, $count) {
                                $msg->to($validEmails)->subject("[Bulk Update] {$count} Questions Modified by Academic Admin");
                            });
                        } catch (\Throwable $e) {
                            report($e);
                            Notification::make()
                                ->title('Failed to Dispatch Bulk Email')
                                ->body('Error: ' . $e->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }

                        ActivityLog::record(
                            event: 'question.bulk_reports_emailed',
                            description: "Admin {$adminEmail} sent bulk change notification for {$count} questions to {$recipient}.",
                            properties: [
                                'count' => $count,
                                'recipient' => $recipient,
                                'summary' => $summary,
                                'report_ids' => $records->pluck('id')->all(),
                            ],
                            admin: $admin
                        );

                        Notification::make()
                            ->title("Bulk Change Email Sent ({$count} reports)")
                            ->body("Dispatched summary to {$recipient}.")
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                BulkAction::make('bulkEmailChanges')
                    ->label('Bulk Email Changes (Selected)')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->modalHeading('Bulk Email Question Changes to Mentioned Address')
                    ->modalDescription('Autofetches all selected questions and student reports, and sends a comprehensive change notification to the mentioned email.')
                    ->modalWidth('2xl')
                    ->schema([
                        TextInput::make('recipient_email')
                            ->label('Recipient Email Address')
                            ->default(fn () => Setting::get('audit_notification_emails', 'support.mymerchantnavy@gmail.com'))
                            ->required(),
                        Textarea::make('bulk_changes_summary')
                            ->label('Summary of Changes Made')
                            ->placeholder('e.g. Corrected questions across Selected Tests; updated answer options, fixed typos, verified explanations with curriculum faculty.')
                            ->rows(3)
                            ->required(),
                        Toggle::make('mark_as_resolved')
                            ->label('Mark all selected reports as resolved')
                            ->default(true),
                    ])
                    ->action(function (Collection $records, array $data) {
                        $admin = Auth::guard('admin')->user();
                        $adminEmail = $admin?->email ?? 'admin';
                        $recipient = $data['recipient_email'];
                        $summary = $data['bulk_changes_summary'];
                        $count = $records->count();

                        $questionsList = '';
                        foreach ($records as $index => $rep) {
                            $num = $index + 1;
                            $student = ($rep->user?->name ?: 'Student #'.$rep->user_id).' ('.($rep->user?->email ?: '—').')';
                            $qText = Str::limit(strip_tags((string) ($rep->question?->body ?? '')), 140);
                            $reason = $rep->reason instanceof ReportReason ? $rep->reason->getLabel() : ($rep->reason ?? 'Report');
                            $correctOpt = $rep->question?->options?->firstWhere('is_correct', true);
                            $keyText = $correctOpt ? strip_tags((string) $correctOpt->body) : 'Not set';
                            $questionsList .= "{$num}. Question #{$rep->question_id} ({$rep->test?->title})\n"
                                ."   Raised by: {$student} [Reason: {$reason}]\n"
                                ."   Question: {$qText}\n"
                                ."   Answer Key: {$keyText}\n\n";

                            if ($data['mark_as_resolved'] ?? false) {
                                $rep->update(['status' => ReportStatus::Resolved]);
                            }
                        }

                        $bulkData = [
                            'count' => $count,
                            'adminName' => $admin?->name ?? 'Academic Admin',
                            'adminEmail' => $adminEmail,
                            'ipAddress' => request()->ip() ?? 'Unknown',
                            'summary' => $summary,
                            'questionsList' => $questionsList,
                            'timestamp' => now()->format('Y-m-d H:i:s'),
                        ];

                        $emails = array_filter(array_map('trim', preg_split('/[,\s;]+/', (string) $recipient)));
                        $validEmails = array_values(array_filter($emails, fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL)));
                        if (empty($validEmails)) {
                            $validEmails = ['support.mymerchantnavy@gmail.com'];
                        }

                        try {
                            Mail::send('emails.bulk-question-correction', $bulkData, function ($msg) use ($validEmails, $count) {
                                $msg->to($validEmails)->subject("[Bulk Update] {$count} Questions Modified by Academic Admin");
                            });
                        } catch (\Throwable $e) {
                            report($e);
                            Notification::make()
                                ->title('Failed to Dispatch Bulk Email')
                                ->body('Error: ' . $e->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }

                        ActivityLog::record(
                            event: 'question.bulk_reports_emailed',
                            description: "Admin {$adminEmail} sent bulk change notification for {$count} questions to {$recipient}.",
                            properties: [
                                'count' => $count,
                                'recipient' => $recipient,
                                'summary' => $summary,
                                'report_ids' => $records->pluck('id')->all(),
                            ],
                            admin: $admin
                        );

                        Notification::make()
                            ->title("Bulk Change Email Sent ({$count} reports)")
                            ->body("Dispatched summary to {$recipient}.")
                            ->success()
                            ->send();
                    })->deselectRecordsAfterCompletion(),

                BulkAction::make('resolveSelected')
                    ->label('Bulk Resolve & Email Students')
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
        $chosenIndex = $chosenId ? $options->search(fn ($option) => $option->id === $chosenId) : false;
        $correctIndex = $options->search(fn ($option) => (bool) $option->is_correct);

        return view('filament.question-report', [
            'report' => $record,
            'question' => $question,
            'body' => $question?->body,
            'optionsList' => $options,
            'correctIndex' => $correctIndex !== false ? $correctIndex : null,
            'chosenIndex' => $chosenIndex !== false ? $chosenIndex : null,
            'correctOptionId' => $correctIndex !== false ? $options[$correctIndex]->id : null,
            'chosenOptionId' => $chosenId,
            'explanation' => $question?->explanation,
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
