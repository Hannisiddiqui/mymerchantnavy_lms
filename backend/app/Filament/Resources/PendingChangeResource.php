<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PendingChangeResource\Pages;
use App\Models\ActivityLog;
use App\Models\PendingChange;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class PendingChangeResource extends Resource
{
    protected static ?string $model = PendingChange::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-check-badge';

    protected static string|\UnitEnum|null $navigationGroup = 'Super Admin';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Pending Approvals';

    protected static ?string $modelLabel = 'Pending Change';

    protected static ?string $pluralModelLabel = 'Pending Changes';

    public static function canAccess(): bool
    {
        return auth('admin')->user()?->isSuperAdmin() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->disabled(),
            TextInput::make('admin_name')->label('Proposed By')->disabled(),
            TextInput::make('action_type')->disabled(),
            TextInput::make('model_type')->disabled(),
            Textarea::make('rejection_reason')->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->latest())
            ->columns([
                TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime('d M Y, H:i')
                    ->description(fn (PendingChange $record) => $record->created_at?->diffForHumans())
                    ->sortable(),

                TextColumn::make('title')
                    ->label('Change Description')
                    ->weight('bold')
                    ->searchable(),

                TextColumn::make('model_type')
                    ->label('Module')
                    ->badge()
                    ->color('info'),

                TextColumn::make('admin_name')
                    ->label('Submitted By')
                    ->description(fn (PendingChange $record) => $record->admin_email)
                    ->searchable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'warning',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending Approval',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Approve & Go Live')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Approve & Publish This Change')
                    ->modalDescription(fn (PendingChange $record) => "Are you sure you want to approve the change '{$record->title}' submitted by {$record->admin_name}? The change will become active immediately.")
                    ->visible(fn (PendingChange $record) => $record->status === 'pending')
                    ->action(function (PendingChange $record) {
                        $superAdmin = Auth::guard('admin')->user();
                        $record->update([
                            'status' => 'approved',
                            'reviewed_by_admin_id' => $superAdmin?->id,
                            'reviewed_at' => now(),
                        ]);

                        ActivityLog::record(
                            event: 'change_proposal.approved',
                            description: "Super Admin approved change '{$record->title}' proposed by {$record->admin_name}.",
                            properties: [
                                'change_id' => $record->id,
                                'model' => $record->model_type,
                            ],
                            admin: $superAdmin
                        );

                        Notification::make()
                            ->title('Change Approved & Published')
                            ->body("'{$record->title}' has been approved and marked live.")
                            ->success()
                            ->send();
                    }),

                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (PendingChange $record) => $record->status === 'pending')
                    ->modalHeading('Reject Proposed Change')
                    ->form([
                        Textarea::make('rejection_reason')
                            ->label('Reason for Rejection')
                            ->placeholder('Please state why this change was not approved...')
                            ->required(),
                    ])
                    ->action(function (PendingChange $record, array $data) {
                        $superAdmin = Auth::guard('admin')->user();
                        $record->update([
                            'status' => 'rejected',
                            'rejection_reason' => $data['rejection_reason'],
                            'reviewed_by_admin_id' => $superAdmin?->id,
                            'reviewed_at' => now(),
                        ]);

                        ActivityLog::record(
                            event: 'change_proposal.rejected',
                            description: "Super Admin rejected change '{$record->title}' proposed by {$record->admin_name}.",
                            properties: [
                                'change_id' => $record->id,
                                'reason' => $data['rejection_reason'],
                            ],
                            admin: $superAdmin
                        );

                        Notification::make()
                            ->title('Change Proposal Rejected')
                            ->body('Rejection recorded with your remarks.')
                            ->warning()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPendingChanges::route('/'),
        ];
    }
}

