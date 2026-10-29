<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomPageResource\Pages;
use App\Models\CustomPage;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class CustomPageResource extends Resource
{
    protected static ?string $model = CustomPage::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-plus';

    protected static string|\UnitEnum|null $navigationGroup = 'Learning';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Page Builder';

    protected static ?string $modelLabel = 'Custom Page';

    protected static ?string $pluralModelLabel = 'Custom Pages';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Page Settings & Navigation')
                ->description('Configure page title, URL slug, sidebar visibility, and icon.')
                ->schema([
                    TextInput::make('title')
                        ->label('Page Title')
                        ->required()
                        ->maxLength(180)
                        ->placeholder('e.g. Maritime Scholarship 2027, Exam Blueprints, Medical Standards')
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Set $set, ?string $state, string $operation) => $operation === 'create' ? $set('slug', Str::slug($state)) : null),

                    TextInput::make('slug')
                        ->label('URL Slug')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->helperText('Will be accessible at /pages/{slug} on the student portal.'),

                    Select::make('icon')
                        ->label('Sidebar Navigation Icon')
                        ->options([
                            'heroicon-o-document-text' => 'Document / Notes (File)',
                            'heroicon-o-academic-cap' => 'Academic Cap (Education)',
                            'heroicon-o-book-open' => 'Book Open (Curriculum)',
                            'heroicon-o-shield-check' => 'Shield Check (Standards & Rules)',
                            'heroicon-o-star' => 'Star (Featured / Highlights)',
                            'heroicon-o-trophy' => 'Trophy (Achievements & Awards)',
                            'heroicon-o-bell' => 'Bell (Announcements)',
                            'heroicon-o-information-circle' => 'Info Circle (Guides & FAQs)',
                            'heroicon-o-newspaper' => 'Newspaper (Updates & News)',
                            'heroicon-o-sparkles' => 'Sparkles (Special Programs)',
                        ])
                        ->default('heroicon-o-document-text')
                        ->searchable(),

                    TextInput::make('badge_text')
                        ->label('Sidebar Badge (Optional)')
                        ->placeholder('e.g. New, 2027, Live')
                        ->maxLength(20),

                    Toggle::make('is_published')
                        ->label('Published (Live on LMS)')
                        ->default(true)
                        ->inline(false)
                        ->helperText('When published, students can view this page.'),

                    Toggle::make('show_in_sidebar')
                        ->label('Show in Student Sidebar')
                        ->default(true)
                        ->inline(false)
                        ->helperText('Displays a direct link in the student left navigation menu under "Resources / Pages".'),

                    TextInput::make('sort_order')
                        ->label('Sidebar Sort Order')
                        ->numeric()
                        ->default(0)
                        ->helperText('Lower numbers appear higher up in the sidebar.'),
                ])->columns(3),

            Section::make('Visual Page Builder')
                ->description('Build rich, dynamic layouts with drag-and-drop sections (Hero Banners, Feature Grids, FAQ Accordions, Rich Text, and Call-to-Action Boxes).')
                ->collapsible()
                ->schema([
                    Builder::make('blocks')
                        ->label('Page Sections')
                        ->blocks([
                            Builder\Block::make('hero')
                                ->label('Hero Announcement Banner')
                                ->icon('heroicon-o-sparkles')
                                ->schema([
                                    TextInput::make('badge')
                                        ->label('Top Eyebrow Badge')
                                        ->placeholder('e.g. SPECIAL ANNOUNCEMENT 2027'),
                                    TextInput::make('title')
                                        ->label('Headline')
                                        ->required()
                                        ->placeholder('e.g. Complete Maritime Scholarship Exam Guide'),
                                    Textarea::make('subtitle')
                                        ->label('Subtitle / Description')
                                        ->rows(2)
                                        ->placeholder('Detailed syllabus breakdown, qualifying marks, and test schedules for all aspiring cadets.'),
                                    TextInput::make('cta_text')
                                        ->label('CTA Button Text')
                                        ->placeholder('e.g. Enroll Now / View Batches'),
                                    TextInput::make('cta_url')
                                        ->label('CTA Button URL')
                                        ->placeholder('/batches or https://...'),
                                    Select::make('bg_theme')
                                        ->label('Banner Color Theme')
                                        ->options([
                                            'navy' => 'Deep Navy (High Contrast)',
                                            'royal' => 'Royal Blue Gradient',
                                            'emerald' => 'Emerald Green',
                                            'amber' => 'Amber Glow',
                                        ])
                                        ->default('navy'),
                                ])->columns(2),

                            Builder\Block::make('rich_text')
                                ->label('Rich Text / Article Section')
                                ->icon('heroicon-o-pencil-square')
                                ->schema([
                                    TextInput::make('heading')
                                        ->label('Section Heading (Optional)')
                                        ->placeholder('e.g. Eligibility Criteria & Guidelines'),
                                    RichEditor::make('body')
                                        ->label('Body Content')
                                        ->required(),
                                ]),

                            Builder\Block::make('cards_grid')
                                ->label('Feature / Cards Grid')
                                ->icon('heroicon-o-squares-2x2')
                                ->schema([
                                    TextInput::make('section_title')
                                        ->label('Grid Section Title')
                                        ->placeholder('e.g. Why Join This Program?'),
                                    Select::make('columns')
                                        ->label('Columns')
                                        ->options([
                                            '2' => '2 Columns',
                                            '3' => '3 Columns',
                                            '4' => '4 Columns',
                                        ])
                                        ->default('3'),
                                    Repeater::make('cards')
                                        ->label('Cards')
                                        ->schema([
                                            TextInput::make('title')
                                                ->label('Card Title')
                                                ->required(),
                                            Textarea::make('description')
                                                ->label('Description')
                                                ->rows(2),
                                            TextInput::make('badge')
                                                ->label('Tag / Badge (Optional)'),
                                            TextInput::make('link_url')
                                                ->label('Card Link (Optional)'),
                                        ])
                                        ->columns(2)
                                        ->collapsible(),
                                ]),

                            Builder\Block::make('faq')
                                ->label('FAQ / Accordion')
                                ->icon('heroicon-o-question-mark-circle')
                                ->schema([
                                    TextInput::make('section_title')
                                        ->label('Section Title')
                                        ->default('Frequently Asked Questions'),
                                    Repeater::make('items')
                                        ->label('Q&A Items')
                                        ->schema([
                                            TextInput::make('question')
                                                ->label('Question')
                                                ->required(),
                                            Textarea::make('answer')
                                                ->label('Answer')
                                                ->rows(3)
                                                ->required(),
                                        ])
                                        ->collapsible(),
                                ]),

                            Builder\Block::make('cta_box')
                                ->label('Call to Action Banner')
                                ->icon('heroicon-o-megaphone')
                                ->schema([
                                    TextInput::make('headline')
                                        ->label('Headline')
                                        ->required(),
                                    Textarea::make('description')
                                        ->label('Description')
                                        ->rows(2),
                                    TextInput::make('button_label')
                                        ->label('Button Label')
                                        ->default('Get Started Now'),
                                    TextInput::make('button_url')
                                        ->label('Button URL')
                                        ->default('/batches'),
                                ])->columns(2),

                            Builder\Block::make('alert_notice')
                                ->label('Notice / Callout Box')
                                ->icon('heroicon-o-exclamation-triangle')
                                ->schema([
                                    Select::make('type')
                                        ->label('Alert Type')
                                        ->options([
                                            'info' => 'Info (Blue)',
                                            'success' => 'Success (Green)',
                                            'warning' => 'Warning (Amber)',
                                            'danger' => 'Danger / Important (Red)',
                                        ])
                                        ->default('info'),
                                    TextInput::make('title')
                                        ->label('Alert Title')
                                        ->required(),
                                    Textarea::make('message')
                                        ->label('Alert Message')
                                        ->rows(2)
                                        ->required(),
                                ])->columns(2),
                        ]),
                ]),

            Section::make('Standard Document Body (Optional Fallback)')
                ->description('If you prefer a simple single-page article instead of structured builder blocks, write it here.')
                ->collapsible()
                ->collapsed()
                ->schema([
                    RichEditor::make('content')
                        ->label('Full Page Article Content')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Page Title')
                    ->description(fn (CustomPage $record) => "/pages/{$record->slug}")
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('icon')
                    ->label('Icon')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('badge_text')
                    ->label('Badge')
                    ->badge()
                    ->color('info')
                    ->placeholder('—'),

                ToggleColumn::make('show_in_sidebar')
                    ->label('In Sidebar'),

                ToggleColumn::make('is_published')
                    ->label('Published'),

                TextColumn::make('sort_order')
                    ->label('Sort')
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomPages::route('/'),
            'create' => Pages\CreateCustomPage::route('/create'),
            'edit' => Pages\EditCustomPage::route('/{record}/edit'),
        ];
    }
}
