<?php

namespace App\Filament\Widgets;

use App\Filament\Support\AssignCourseAction;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Registered students with no course yet: the admin's daily to-do list. */
class StudentsWaiting extends TableWidget
{
    protected static bool $isLazy = false;

    protected static ?string $heading = 'Students waiting for a course';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => User::doesntHave('courses')->latest())
            ->columns([
                TextColumn::make('email')->weight('bold')->placeholder('—'),
                TextColumn::make('name')->placeholder('— profile not completed —'),
                TextColumn::make('target_exam')->label('Target exam')->placeholder('—'),
                TextColumn::make('created_at')->label('Registered')->since(),
            ])
            ->recordActions([
                AssignCourseAction::make(fn (Action $action) => $action->getRecord()),
            ])
            ->paginated([5, 10])
            ->emptyStateHeading('Everyone has a course');
    }
}
