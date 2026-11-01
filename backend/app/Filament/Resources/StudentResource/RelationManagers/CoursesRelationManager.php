<?php

namespace App\Filament\Resources\StudentResource\RelationManagers;

use App\Models\Course;
use App\Services\Courses\EnrollmentService;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Courses this student can access. Courses arrive only through purchases (Razorpay via WordPress),
 * so there is no "assign" button here; an admin can still remove access.
 */
class CoursesRelationManager extends RelationManager
{
    protected static string $relationship = 'courses';

    protected static ?string $title = 'Purchased courses';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->weight('bold'),
                TextColumn::make('pivot.purchase_date')->label('Purchased on')->dateTime('d M Y, h:i A')->placeholder('—'),
                TextColumn::make('pivot.amount')->label('Paid')->money('INR')->placeholder('—'),
                TextColumn::make('pivot.expires_at')->label('Valid until')->dateTime('d M Y')->placeholder('Lifetime'),
            ])
            ->recordActions([
                Action::make('remove')->label('Remove access')->icon('heroicon-o-x-mark')->color('danger')->requiresConfirmation()
                    ->action(fn (Course $record) => app(EnrollmentService::class)->remove($this->getOwnerRecord(), $record)),
            ])
            ->emptyStateHeading('No purchases yet')
            ->emptyStateDescription('Courses appear here when the student buys one.');
    }
}
