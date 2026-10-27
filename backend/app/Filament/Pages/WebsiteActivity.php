<?php

namespace App\Filament\Pages;

use App\Models\ActivityLog;
use Filament\Actions\Action;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Super-Admin-only page to inspect all activity across the website
 * performed by other admins and students (changes, logins, deletions, test attempts, settings).
 */
class WebsiteActivity extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-command-line';

    protected static string|\UnitEnum|null $navigationGroup = 'Super Admin';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Website Activity & Audit Log';

    protected static ?string $navigationLabel = 'Website Activity';

    protected string $view = 'filament.pages.website-activity';

    public static function canAccess(): bool
    {
        return auth('admin')->user()?->isSuperAdmin() ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => ActivityLog::query())
            ->columns([
                TextColumn::make('created_at')
                    ->label('Timestamp')
                    ->dateTime('d M Y, H:i:s')
                    ->description(fn (ActivityLog $record) => $record->created_at?->diffForHumans())
                    ->sortable(),
                TextColumn::make('actor_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst((string) $state))
                    ->color(fn (string $state): string => match ($state) {
                        'admin' => 'primary',
                        'student' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('actor_email')
                    ->label('Actor')
                    ->searchable()
                    ->weight('bold')
                    ->description(function (ActivityLog $record) {
                        $name = $record->actor_name ?: 'Unknown';
                        $role = $record->actor_role ? " [{$record->actor_role}]" : '';

                        return "{$name}{$role}";
                    }),
                TextColumn::make('event')
                    ->label('Action / Event')
                    ->badge()
                    ->searchable()
                    ->color(fn (string $state): string => match (true) {
                        str_contains($state, 'deleted') || str_contains($state, 'danger') => 'danger',
                        str_contains($state, 'login') || str_contains($state, 'verified') || str_contains($state, 'completed') => 'success',
                        str_contains($state, 'role') || str_contains($state, 'limit') || str_contains($state, 'deactivate') => 'warning',
                        default => 'info',
                    }),
                TextColumn::make('description')
                    ->label('Activity Description')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->toggleable()
                    ->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('actor_type')
                    ->label('Actor Type')
                    ->options([
                        'admin' => 'Admins Only',
                        'student' => 'Students Only',
                        'system' => 'System Automated',
                    ]),
                SelectFilter::make('event')
                    ->label('Event Category')
                    ->options([
                        'student.deleted' => 'Student Deleted',
                        'student.login' => 'Student Login',
                        'admin.login' => 'Admin Login',
                        'admin.role_changed' => 'Admin Role Changed',
                        'admin.session_limit_updated' => 'Session Limit Updated',
                        'test.attempt_started' => 'Test Started',
                        'test.attempt_submitted' => 'Test Submitted',
                    ]),
            ])
            ->recordActions([
                Action::make('viewDetails')
                    ->label('Details')
                    ->icon('heroicon-o-information-circle')
                    ->color('gray')
                    ->modalHeading('Activity Event Details')
                    ->form([
                        TextInput::make('created_at')
                            ->label('Timestamp')
                            ->formatStateUsing(fn ($record) => $record->created_at?->format('d M Y, H:i:s').' ('.$record->created_at?->diffForHumans().')')
                            ->disabled(),
                        TextInput::make('actor_info')
                            ->label('Performed By')
                            ->formatStateUsing(fn ($record) => "{$record->actor_name} ({$record->actor_email}) — Role: {$record->actor_role} [{$record->actor_type}]")
                            ->disabled(),
                        TextInput::make('event')
                            ->label('Event Name')
                            ->disabled(),
                        Textarea::make('description')
                            ->label('Full Description')
                            ->rows(3)
                            ->disabled(),
                        TextInput::make('ip_address')
                            ->label('IP Address')
                            ->disabled(),
                        Textarea::make('user_agent')
                            ->label('Browser / Device (User Agent)')
                            ->rows(2)
                            ->disabled(),
                        KeyValue::make('properties')
                            ->label('Event Metadata & Changes')
                            ->disabled(),
                    ])
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ]);
    }
}
