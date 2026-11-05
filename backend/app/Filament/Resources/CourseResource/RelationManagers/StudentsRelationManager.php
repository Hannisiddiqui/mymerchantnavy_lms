<?php

namespace App\Filament\Resources\CourseResource\RelationManagers;

use App\Models\User;
use App\Services\Courses\EnrollmentService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

/** Students who bought this course. Access comes only from purchases; an admin can remove it here. */
class StudentsRelationManager extends RelationManager
{
    protected static string $relationship = 'students';

    protected static ?string $title = 'Students who bought this';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->where('users.is_preview', false))
            ->columns([
                TextColumn::make('email')->searchable(),
                TextColumn::make('name')->searchable()->placeholder('— profile not completed —'),
                TextColumn::make('phone')->label('Mobile')->toggleable()->placeholder('—'),
                TextColumn::make('pivot.purchase_date')->label('Purchased on')->dateTime('d M Y, h:i A')->placeholder('—'),
                TextColumn::make('pivot.amount')->label('Paid')->money('INR')->placeholder('—'),
                TextColumn::make('pivot.expires_at')->label('Valid until')->dateTime('d M Y')->placeholder('Lifetime'),
            ])
            ->recordActions([
                Action::make('remove')->label('Remove access')->icon('heroicon-o-x-mark')->color('danger')->requiresConfirmation()
                    ->action(fn (User $record) => app(EnrollmentService::class)->remove($record, $this->getOwnerRecord())),
            ])
            ->toolbarActions([
                BulkAction::make('remove')->label('Remove access')->color('danger')->requiresConfirmation()
                    ->action(fn (Collection $records) => $records->each(fn (User $u) => app(EnrollmentService::class)->remove($u, $this->getOwnerRecord()))),
            ]);
    }
}
