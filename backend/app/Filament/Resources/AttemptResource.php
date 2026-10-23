<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AttemptResource\Pages\ListAttempts;
use App\Models\TestAttempt;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Report: every test attempt by every student (read-only). */
class AttemptResource extends Resource
{
    protected static ?string $model = TestAttempt::class;

    protected static ?string $modelLabel = 'result';

    protected static ?string $navigationLabel = 'Test results';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['user', 'test'])->whereNotNull('submitted_at'))
            ->columns([
                TextColumn::make('user.name')->label('Student')->searchable()->description(fn ($record) => $record->user->loginId()),
                TextColumn::make('test.title')->label('Test')->searchable()->wrap(),
                TextColumn::make('attempt_no')->label('Attempt')->sortable(),
                TextColumn::make('score')->sortable()->weight('bold'),
                TextColumn::make('correct')->color('success')->sortable(),
                TextColumn::make('incorrect')->color('danger')->sortable(),
                TextColumn::make('skipped'),
                TextColumn::make('time_taken_seconds')->label('Time')->sortable()->formatStateUsing(fn ($state) => gmdate('H:i:s', (int) $state)),
                TextColumn::make('submitted_at')->label('Submitted')->dateTime('d M Y, h:i A')->sortable(),
            ])
            ->defaultSort('submitted_at', 'desc')
            ->filters([
                SelectFilter::make('test_id')->label('Test')->relationship('test', 'title')->searchable()->preload(),
                SelectFilter::make('attempt_no')->label('Attempt')->options([1 => 'First attempt only']),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListAttempts::route('/')];
    }
}
