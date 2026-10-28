<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QuestionImportResource\Pages;
use App\Models\QuestionImport;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

/**
 * Question imports: files that were read into DRAFT questions for review.
 * History list here; the review screen is Pages/ReviewImport.php.
 */
class QuestionImportResource extends Resource
{
    protected static ?string $model = QuestionImport::class;

    protected static ?string $navigationLabel = 'Question imports';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static string|\UnitEnum|null $navigationGroup = 'Learning';

    protected static ?int $navigationSort = 4;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('original_name')->label('File')->weight('bold')->searchable()->wrap(),
                TextColumn::make('method')->badge()->formatStateUsing(fn ($state) => $state->label()),
                TextColumn::make('status')->badge()->color(fn ($state) => match ($state) {'ready' => 'success', 'failed' => 'danger', default => 'warning'})
                    ->formatStateUsing(fn ($state, QuestionImport $r) => $state === 'parsing' ? "Reading… {$r->progress}%" : ucfirst($state)),
                TextColumn::make('drafts_count')->counts('drafts')->label('Questions'),
                TextColumn::make('added')->label('Added')->color('success')
                    ->state(fn (QuestionImport $record) => $record->drafts()->where('status', 'added')->count()),
                TextColumn::make('test.title')->label('For test')->placeholder('—')->toggleable(),
                TextColumn::make('created_at')->label('Imported')->since()->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->poll('5s')
            ->recordActions([
                Action::make('review')->label('Review')->icon('heroicon-o-clipboard-document-check')->color('primary')
                    ->url(fn (QuestionImport $record) => static::getUrl('review', ['record' => $record])),
                DeleteAction::make()->label('Discard')->modalDescription('The draft list is deleted. Questions already added to tests are kept.')
                    ->after(fn (QuestionImport $record) => Storage::disk('local')->delete($record->file_path)),
            ])
            ->recordUrl(fn (QuestionImport $record) => static::getUrl('review', ['record' => $record]))
            ->emptyStateHeading('No imports yet')
            ->emptyStateDescription('Use “Import from PDF or Excel” or “Parse PDF with AI” to read questions from a file.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQuestionImports::route('/'),
            'review' => Pages\ReviewImport::route('/{record}/review'),
        ];
    }
}
