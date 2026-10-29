<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupportQueryResource\Pages;
use App\Models\ActivityLog;
use App\Models\SupportQuery;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class SupportQueryResource extends Resource
{
    protected static ?string $model = SupportQuery::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static string|\UnitEnum|null $navigationGroup = 'People';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Support & WhatsApp Queries';

    protected static ?string $modelLabel = 'Student Query';

    protected static ?string $pluralModelLabel = 'Support Queries';

    public static function canViewAny(): bool
    {
        $admin = auth('admin')->user();
        if (! $admin) {
            return false;
        }

        return $admin->isSuperAdmin() || $admin->isSiteManager() || $admin->hasPermission('manage_support_chat');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->disabled(),
            TextInput::make('email')->disabled(),
            TextInput::make('phone')->disabled(),
            TextInput::make('category')->disabled(),
            Textarea::make('message')->disabled()->rows(3)->columnSpanFull(),
            Select::make('status')
                ->options([
                    'open' => 'Open',
                    'in_progress' => 'In Progress',
                    'resolved' => 'Resolved',
                    'closed' => 'Closed',
                ])
                ->required(),
            Textarea::make('admin_reply')
                ->label('Faculty / Site Manager Reply')
                ->rows(4)
                ->columnSpanFull()
                ->helperText('This reply will be sent to the student and visible inside their in-app chat widget.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->latest())
            ->columns([
                TextColumn::make('created_at')
                    ->label('Received')
                    ->dateTime('d M Y, H:i')
                    ->description(fn (SupportQuery $record) => $record->created_at?->diffForHumans())
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Student')
                    ->description(fn (SupportQuery $record) => $record->email)
                    ->searchable(['name', 'email'])
                    ->weight('bold'),

                TextColumn::make('phone')
                    ->label('Phone / WhatsApp')
                    ->formatStateUsing(fn ($state) => $state ? "+{$state}" : '—')
                    ->searchable(),

                TextColumn::make('category')
                    ->label('Topic')
                    ->badge()
                    ->color('info')
                    ->searchable(),

                TextColumn::make('channel')
                    ->label('Channel')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'whatsapp' => 'WhatsApp',
                        'email' => 'Email',
                        default => 'Web Chat',
                    })
                    ->color(fn ($state) => match ($state) {
                        'whatsapp' => 'success',
                        'email' => 'warning',
                        default => 'primary',
                    }),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'resolved' => 'success',
                        'in_progress' => 'warning',
                        'closed' => 'gray',
                        default => 'danger',
                    }),

                TextColumn::make('admin_reply')
                    ->label('Reply Status')
                    ->formatStateUsing(fn ($state) => filled($state) ? 'Replied' : 'Pending Reply')
                    ->badge()
                    ->color(fn ($state) => filled($state) ? 'success' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'open' => 'Open',
                        'in_progress' => 'In Progress',
                        'resolved' => 'Resolved',
                    ]),
                SelectFilter::make('category')
                    ->options([
                        'IMU-CET' => 'IMU-CET',
                        'DNS Sponsorship' => 'DNS Sponsorship',
                        'G.P. Rating' => 'G.P. Rating',
                        'General Inquiry' => 'General Inquiry',
                        'Technical' => 'Technical Support',
                    ]),
            ])
            ->recordActions([
                Action::make('reply')
                    ->label('Reply')
                    ->icon('heroicon-o-chat-bubble-left')
                    ->color('primary')
                    ->modalHeading(fn (SupportQuery $record) => "Reply to {$record->name}")
                    ->modalDescription(fn (SupportQuery $record) => "Topic: {$record->category} • Received {$record->created_at?->format('d M, H:i')}")
                    ->modalWidth('xl')
                    ->fillForm(fn (SupportQuery $record) => [
                        'admin_reply' => $record->admin_reply,
                        'status' => $record->status === 'open' ? 'resolved' : $record->status,
                        'send_email' => true,
                    ])
                    ->form([
                        TextInput::make('student_msg')
                            ->label('Student Inquiry')
                            ->formatStateUsing(fn ($record) => $record->message)
                            ->disabled()
                            ->columnSpanFull(),

                        Textarea::make('admin_reply')
                            ->label('Your Response (Sent to Student)')
                            ->rows(4)
                            ->required()
                            ->placeholder('Type your answer, guidance, or resolution for the student...')
                            ->columnSpanFull(),

                        Select::make('status')
                            ->label('Query Status')
                            ->options([
                                'in_progress' => 'In Progress (Awaiting more info)',
                                'resolved' => 'Resolved (Answer provided)',
                                'closed' => 'Closed',
                            ])
                            ->required(),

                        Toggle::make('send_email')
                            ->label('Send Reply Notification Email to Student')
                            ->default(true),
                    ])
                    ->action(function (SupportQuery $record, array $data) {
                        $admin = Auth::guard('admin')->user();
                        $reply = $data['admin_reply'];
                        $status = $data['status'];
                        $sendEmail = (bool) ($data['send_email'] ?? true);

                        $record->update([
                            'admin_reply' => $reply,
                            'status' => $status,
                            'replied_by_admin_id' => $admin?->id,
                            'replied_at' => now(),
                        ]);

                        if ($sendEmail && filter_var($record->email, FILTER_VALIDATE_EMAIL)) {
                            $emailData = [
                                'studentName' => $record->name,
                                'category' => $record->category,
                                'originalMessage' => $record->message,
                                'replyMessage' => $reply,
                                'facultyName' => $admin?->name ?? 'Academic Support Team',
                                'timestamp' => now()->format('Y-m-d H:i:s'),
                            ];

                            try {
                                Mail::send('emails.support-reply', $emailData, function ($msg) use ($record) {
                                    $msg->to($record->email)
                                        ->subject("[My Merchant Navy] Response to your Inquiry: {$record->category}");
                                });
                            } catch (\Throwable $e) {
                                report($e);
                            }
                        }

                        ActivityLog::record(
                            event: 'support.query_replied',
                            description: "Site Manager {$admin?->name} replied to query #{$record->id} from {$record->name}.",
                            properties: [
                                'query_id' => $record->id,
                                'student_name' => $record->name,
                                'status' => $status,
                            ],
                            admin: $admin
                        );

                        Notification::make()
                            ->title('Reply Dispatched')
                            ->body("Your response was recorded and sent to {$record->name}.")
                            ->success()
                            ->send();
                    }),

                Action::make('whatsapp')
                    ->label('WhatsApp')
                    ->icon('heroicon-o-chat-bubble-oval-left-ellipsis')
                    ->color('success')
                    ->url(function (SupportQuery $record) {
                        $clean = preg_replace('/\D/', '', (string) $record->phone);
                        if (! str_starts_with($clean, '91') && strlen($clean) === 10) {
                            $clean = '91'.$clean;
                        }
                        $msg = urlencode("Hello {$record->name}, this is from My Merchant Navy regarding your inquiry ({$record->category}). How can we assist you further?");

                        return "https://wa.me/{$clean}?text={$msg}";
                    })
                    ->openUrlInNewTab()
                    ->visible(fn (SupportQuery $record) => filled($record->phone)),

                DeleteAction::make()
                    ->visible(fn () => auth('admin')->user()?->isSuperAdmin()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSupportQueries::route('/'),
        ];
    }
}

