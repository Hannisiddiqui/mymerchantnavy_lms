<?php

namespace App\Filament\Resources;

use App\Filament\Support\SuperAdminOnlyDeletes;
use App\Filament\Resources\CustomPageResource\Pages;
use App\Models\Batch;
use App\Models\Course;
use App\Models\CustomPage;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
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
    use SuperAdminOnlyDeletes;

    protected static ?string $model = CustomPage::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-plus';

    protected static string|\UnitEnum|null $navigationGroup = 'Learning';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Page Builder';

    protected static ?string $modelLabel = 'Custom Page';

    protected static ?string $pluralModelLabel = 'Custom Pages';

    public static function canViewAny(): bool
    {
        $admin = auth('admin')->user();
        if (! $admin) {
            return false;
        }

        return $admin->isSuperAdmin() || $admin->hasPermission('manage_page_builder');
    }

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

            Section::make('Look, Layout & Who Can See It')
                ->description('Make the page feel like yours: colour theme, width, cover image, schedule and who is allowed to open it.')
                ->collapsible()
                ->schema([
                    Select::make('theme')->label('Colour theme')->options([
                        'brand' => 'Brand blue', 'navy' => 'Deep navy', 'emerald' => 'Emerald green', 'violet' => 'Violet', 'amber' => 'Amber / gold', 'rose' => 'Rose red',
                    ])->default('brand')->native(false)->helperText('Colours the accents (buttons, badges, highlights) on this page.'),
                    Select::make('width')->label('Page width')->options([
                        'narrow' => 'Narrow (reading article)', 'normal' => 'Normal', 'wide' => 'Wide', 'full' => 'Full width',
                    ])->default('normal')->native(false),
                    Toggle::make('show_header')->label('Show the title bar')->default(true)->inline(false)
                        ->helperText('Turn off when your first section (for example a Hero) already carries the title.'),
                    TextInput::make('cover_image_url')->label('Cover image (optional)')->url()->placeholder('https://… banner shown above the sections')->columnSpan(2),
                    Select::make('access')->label('Who can open this page')->options([
                        'students' => 'Every logged-in student',
                        'courses' => 'Only students enrolled in selected courses',
                        'public' => 'Anyone with the link (even before logging in)',
                    ])->default('students')->native(false)->live(),
                    Select::make('access_course_ids')->label('Courses allowed')->multiple()->searchable()->preload()
                        ->options(fn () => Course::where('is_active', true)->orderBy('title')->pluck('title', 'id'))
                        ->visible(fn ($get) => $get('access') === 'courses')->required(fn ($get) => $get('access') === 'courses')
                        ->helperText('Only students whose access to one of these courses is still valid see the page.'),
                    DateTimePicker::make('publish_at')->label('Go live on (optional)')->seconds(false)->helperText('Leave empty to publish immediately.'),
                    DateTimePicker::make('unpublish_at')->label('Hide on (optional)')->seconds(false)->after('publish_at')->helperText('For offers and notices that should disappear by themselves.'),
                ])->columns(3),

            Section::make('Visual Page Builder')
                ->description('Build the page from sections: add, drag to reorder, duplicate or collapse each one. 22 section types: banners, text, columns, images, galleries, video, tables, timelines, FAQs, downloads, countdowns, batches for sale and more.')
                ->collapsible()
                ->schema([
                    Builder::make('blocks')
                        ->label('Page Sections')
                        ->collapsible()
                        ->cloneable()
                        ->blockNumbers(false)
                        ->addActionLabel('Add a section')
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

                            Builder\Block::make('media_image')
                                ->label('Photo / Graphic (Upload or URL)')
                                ->icon('heroicon-o-photo')
                                ->schema([
                                    FileUpload::make('image_file')
                                        ->label('Upload Image from Computer')
                                        ->image()
                                        ->directory('pages')
                                        ->disk('public')
                                        ->helperText('PNG, JPG, or WEBP up to 5MB.'),
                                    TextInput::make('image_url')
                                        ->label('Or Image URL (Unsplash / CDN / External Link)')
                                        ->url()
                                        ->placeholder('https://...'),
                                    TextInput::make('caption')
                                        ->label('Image Caption (Optional)')
                                        ->placeholder('e.g. Navigation Bridge Practical Training'),
                                    Select::make('alignment')
                                        ->label('Layout Alignment')
                                        ->options([
                                            'center' => 'Center',
                                            'full' => 'Full Width',
                                            'left' => 'Float Left',
                                            'right' => 'Float Right',
                                        ])
                                        ->default('center'),
                                ])->columns(2),

                            Builder\Block::make('split_content')
                                ->label('Split Layout (Image + Text)')
                                ->icon('heroicon-o-view-columns')
                                ->schema([
                                    TextInput::make('heading')
                                        ->label('Section Heading')
                                        ->required(),
                                    Select::make('image_position')
                                        ->label('Image Position')
                                        ->options([
                                            'left' => 'Image on Left, Text on Right',
                                            'right' => 'Text on Left, Image on Right',
                                        ])
                                        ->default('left'),
                                    FileUpload::make('image_file')
                                        ->label('Upload Image')
                                        ->image()
                                        ->directory('pages')
                                        ->disk('public'),
                                    TextInput::make('image_url')
                                        ->label('Or Image URL')
                                        ->url(),
                                    RichEditor::make('body')
                                        ->label('Section Description & Highlights')
                                        ->columnSpanFull(),
                                    TextInput::make('button_text')
                                        ->label('CTA Button Text')
                                        ->placeholder('e.g. Learn More'),
                                    TextInput::make('button_url')
                                        ->label('CTA Button URL')
                                        ->placeholder('/batches'),
                                ])->columns(2),

                            Builder\Block::make('stats_numbers')
                                ->label('Achievement Numbers & Statistics')
                                ->icon('heroicon-o-chart-bar')
                                ->schema([
                                    TextInput::make('section_title')
                                        ->label('Section Heading')
                                        ->default('Excellence in Maritime Education'),
                                    Repeater::make('stats')
                                        ->label('Statistics Counters')
                                        ->schema([
                                            TextInput::make('value')
                                                ->label('Stat Value (e.g. 5,000+, 98.4%)')
                                                ->required(),
                                            TextInput::make('label')
                                                ->label('Stat Label (e.g. Qualified Cadets)')
                                                ->required(),
                                            TextInput::make('description')
                                                ->label('Subtext (Optional)'),
                                        ])
                                        ->columns(3),
                                ]),

                            Builder\Block::make('testimonials')
                                ->label('Student Testimonials & Reviews')
                                ->icon('heroicon-o-chat-bubble-bottom-center-text')
                                ->schema([
                                    TextInput::make('section_title')
                                        ->label('Section Heading')
                                        ->default('Student Success Stories'),
                                    Repeater::make('reviews')
                                        ->label('Testimonial Cards')
                                        ->schema([
                                            TextInput::make('name')
                                                ->label('Student Name')
                                                ->required(),
                                            TextInput::make('rank')
                                                ->label('Rank / Batch (e.g. IMU-CET AIR 4)'),
                                            Textarea::make('feedback')
                                                ->label('Review Text')
                                                ->required()
                                                ->rows(2),
                                            TextInput::make('avatar_url')
                                                ->label('Avatar Photo URL (Optional)')
                                                ->url(),
                                        ])
                                        ->columns(2),
                                ]),

                            Builder\Block::make('video_embed')
                                ->label('Video Embed (YouTube / Vimeo / MP4)')
                                ->icon('heroicon-o-video-camera')
                                ->schema([
                                    TextInput::make('video_url')
                                        ->label('YouTube, Vimeo or MP4 URL')
                                        ->required()
                                        ->placeholder('https://www.youtube.com/watch?v=...'),
                                    TextInput::make('title')
                                        ->label('Video Title'),
                                    Textarea::make('caption')
                                        ->label('Overview / Notes')
                                        ->rows(2),
                                ])->columns(2),

                            Builder\Block::make('section_heading')
                                ->label('Heading')
                                ->icon('heroicon-o-bars-3-bottom-left')
                                ->schema([
                                    TextInput::make('title')->label('Heading')->required(),
                                    TextInput::make('subtitle')->label('Small text under it'),
                                    Select::make('align')->label('Alignment')->options(['left' => 'Left', 'center' => 'Centre'])->default('left'),
                                    Select::make('size')->label('Size')->options(['md' => 'Medium', 'lg' => 'Large', 'xl' => 'Extra large'])->default('lg'),
                                ])->columns(2),

                            Builder\Block::make('text_columns')
                                ->label('Text in 2 or 3 columns')
                                ->icon('heroicon-o-view-columns')
                                ->schema([
                                    Select::make('columns')->label('Columns')->options(['2' => '2 columns', '3' => '3 columns'])->default('2'),
                                    Repeater::make('items')->label('Columns')->minItems(2)->maxItems(3)->defaultItems(2)->schema([
                                        TextInput::make('title')->label('Column heading'),
                                        RichEditor::make('body')->label('Text')->required(),
                                    ])->columnSpanFull(),
                                ])->columns(2),

                            Builder\Block::make('button_row')
                                ->label('Buttons')
                                ->icon('heroicon-o-cursor-arrow-rays')
                                ->schema([
                                    Select::make('align')->label('Alignment')->options(['left' => 'Left', 'center' => 'Centre', 'right' => 'Right'])->default('left'),
                                    Repeater::make('buttons')->label('Buttons')->minItems(1)->maxItems(6)->defaultItems(1)->schema([
                                        TextInput::make('label')->required(),
                                        TextInput::make('url')->label('Link')->required()->placeholder('https://… or /my-courses'),
                                        Select::make('style')->options(['primary' => 'Solid', 'outline' => 'Outline', 'dark' => 'Dark'])->default('primary'),
                                        Toggle::make('new_tab')->label('Open in a new tab')->inline(false),
                                    ])->columns(4)->columnSpanFull(),
                                ])->columns(1),

                            Builder\Block::make('image_gallery')
                                ->label('Photo Gallery')
                                ->icon('heroicon-o-squares-2x2')
                                ->schema([
                                    TextInput::make('section_title')->label('Gallery title'),
                                    Select::make('columns')->label('Photos per row')->options(['2' => '2', '3' => '3', '4' => '4'])->default('3'),
                                    Repeater::make('images')->label('Photos')->minItems(1)->schema([
                                        FileUpload::make('image_file')->label('Upload')->image()->directory('pages')->disk('public'),
                                        TextInput::make('image_url')->label('Or image link')->url(),
                                        TextInput::make('caption')->label('Caption'),
                                    ])->columns(3)->columnSpanFull(),
                                ])->columns(2),

                            Builder\Block::make('timeline')
                                ->label('Steps / Timeline / Study Plan')
                                ->icon('heroicon-o-queue-list')
                                ->schema([
                                    TextInput::make('section_title')->label('Title'),
                                    Repeater::make('steps')->label('Steps')->minItems(1)->schema([
                                        TextInput::make('label')->label('Label (Week 1, Step 2, Jan 15…)'),
                                        TextInput::make('title')->label('Step title')->required(),
                                        Textarea::make('description')->rows(2),
                                    ])->columns(3)->columnSpanFull(),
                                ])->columns(1),

                            Builder\Block::make('data_table')
                                ->label('Table')
                                ->icon('heroicon-o-table-cells')
                                ->schema([
                                    TextInput::make('section_title')->label('Title'),
                                    TagsInput::make('columns')->label('Column headings (press Enter after each)')->required()->placeholder('e.g. Subject'),
                                    Repeater::make('rows')->label('Rows')->minItems(1)->schema([
                                        TagsInput::make('cells')->label('Cells, in column order (press Enter after each)')->required(),
                                    ])->columnSpanFull(),
                                ])->columns(1),

                            Builder\Block::make('downloads')
                                ->label('Downloads & Links (PDFs, notes, forms)')
                                ->icon('heroicon-o-arrow-down-tray')
                                ->schema([
                                    TextInput::make('section_title')->label('Title')->default('Resources'),
                                    Repeater::make('files')->label('Files and links')->minItems(1)->schema([
                                        TextInput::make('label')->required(),
                                        TextInput::make('file_url')->label('Link to the file or page')->required()->placeholder('https://…'),
                                        TextInput::make('note')->label('Small note (size, year…)'),
                                    ])->columns(3)->columnSpanFull(),
                                ])->columns(1),

                            Builder\Block::make('batches_showcase')
                                ->label('Batches for Sale (cards with price and Enroll button)')
                                ->icon('heroicon-o-shopping-bag')
                                ->schema([
                                    TextInput::make('section_title')->label('Title')->default('Join a batch'),
                                    Select::make('batch_ids')->label('Batches to show')->multiple()->searchable()->preload()->required()
                                        ->options(fn () => Batch::orderBy('title')->pluck('title', 'id'))
                                        ->helperText('Only batches that are live are shown. Price and link are read from the batch, so they always stay current.'),
                                ])->columns(1),

                            Builder\Block::make('countdown')
                                ->label('Countdown Timer (exam / offer)')
                                ->icon('heroicon-o-clock')
                                ->schema([
                                    TextInput::make('title')->label('What is it counting down to?')->required()->placeholder('IMU-CET 2027 exam'),
                                    DateTimePicker::make('target')->label('Date and time')->required()->seconds(false),
                                    TextInput::make('after_text')->label('Text after it has passed')->default('This event has started.'),
                                ])->columns(2),

                            Builder\Block::make('checklist')
                                ->label('Checklist / Key Points')
                                ->icon('heroicon-o-check-circle')
                                ->schema([
                                    TextInput::make('section_title')->label('Title'),
                                    TagsInput::make('items')->label('Points (press Enter after each)')->required(),
                                    Select::make('style')->options(['check' => 'Ticks', 'bullet' => 'Bullets', 'numbered' => 'Numbers'])->default('check'),
                                ])->columns(1),

                            Builder\Block::make('spacer')
                                ->label('Space / Divider line')
                                ->icon('heroicon-o-minus')
                                ->schema([
                                    Select::make('height')->label('Space')->options(['sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large'])->default('md'),
                                    Toggle::make('line')->label('Show a thin line')->default(true)->inline(false),
                                ])->columns(2),

                            Builder\Block::make('custom_html')
                                ->label('Custom HTML (advanced)')
                                ->icon('heroicon-o-code-bracket')
                                ->schema([
                                    Textarea::make('body')->label('HTML')->rows(8)->required()
                                        ->helperText('For layouts the other sections cannot do. For safety, scripts, forms and unknown embeds are removed automatically; YouTube/Vimeo iframes, images, links, tables and basic styling are kept.'),
                                ])->columns(1),
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

                TextColumn::make('access')->label('Who sees it')->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) { 'public' => 'Anyone', 'courses' => 'Selected courses', default => 'All students' })
                    ->color(fn (?string $state) => $state === 'public' ? 'warning' : ($state === 'courses' ? 'info' : 'success')),

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
                Action::make('open')->label('Open')->icon('heroicon-o-arrow-top-right-on-square')->color('gray')
                    ->url(fn (CustomPage $record) => rtrim((string) config('lms.student_app_url'), '/').'/pages/'.$record->slug, shouldOpenInNewTab: true)
                    ->visible(fn (CustomPage $record) => $record->is_published),
                Action::make('duplicate')->label('Duplicate')->icon('heroicon-o-document-duplicate')->color('gray')
                    ->action(function (CustomPage $record) {
                        $copy = $record->replicate();
                        $copy->title = $record->title.' (copy)';
                        $copy->slug = $record->slug.'-copy-'.Str::lower(Str::random(4));
                        $copy->is_published = false;
                        $copy->save();
                    }),
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

