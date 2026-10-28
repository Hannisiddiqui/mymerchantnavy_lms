<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EnrollmentResource\Pages\ListEnrollments;
use App\Models\Enrollment;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Report: which student got which course, when and by whom (read-only). */
class EnrollmentResource extends Resource
{
    protected static ?string $model = Enrollment::class;

    protected static ?string $navigationLabel = 'Enrollments';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return ! (auth('admin')->user()?->isEditor() ?? false);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['user', 'course', 'assignedBy']))
            ->columns([
                TextColumn::make('user.email')->label('Email')->searchable(),
                TextColumn::make('user.name')->label('Student')->searchable()->placeholder('—'),
                TextColumn::make('course.title')->label('Course')->sortable(),
                TextColumn::make('assignedBy.name')->label('Assigned by')->placeholder('—'),
                TextColumn::make('assigned_at')->label('Assigned on')->dateTime('d M Y, h:i A')->sortable(),
            ])
            ->defaultSort('assigned_at', 'desc')
            ->filters([SelectFilter::make('course_id')->label('Course')->relationship('course', 'title')]);
    }

    public static function getPages(): array
    {
        return ['index' => ListEnrollments::route('/')];
    }
}
