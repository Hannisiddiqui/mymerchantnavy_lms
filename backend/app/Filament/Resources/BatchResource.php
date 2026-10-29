<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BatchResource\Pages;
use App\Models\Batch;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class BatchResource extends Resource
{
    protected static ?string $model = Batch::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static string|\UnitEnum|null $navigationGroup = 'Learning';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Batches';

    protected static ?string $modelLabel = 'Batch';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Batch Identity & Branding')
                ->description('Configure batch title, target exam, PW-style banner image, and bullet highlights.')
                ->schema([
                    TextInput::make('title')
                        ->label('Batch Title')
                        ->required()
                        ->maxLength(180)
                        ->placeholder('e.g. IMU-CET 2027 Live Target Batch')
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Set $set, ?string $state, string $operation) => $operation === 'create' ? $set('slug', Str::slug($state)) : null),

                    TextInput::make('slug')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->helperText('Unique URL identifier for the batch.'),

                    TextInput::make('batch_code')
                        ->label('Batch Code / Tag')
                        ->placeholder('#IMU-2027-LIVE')
                        ->maxLength(60),

                    Select::make('target_exam')
                        ->label('Target Exam / Category')
                        ->options([
                            'IMU-CET' => 'IMU-CET',
                            'DNS Sponsorship' => 'DNS Sponsorship Exam & Interview',
                            'G.P. Rating' => 'G.P. Rating Complete Course',
                            'B.Tech Marine' => 'B.Tech Marine Engineering',
                            'ETO Rating' => 'Electro-Technical Officer (ETO)',
                            'Company Sponsorship' => 'Company Sponsorship Exam',
                            'Class 12th' => 'Class 12th Foundation',
                        ])
                        ->searchable()
                        ->required(),

                    TextInput::make('image_url')
                        ->label('Batch Thumbnail / Banner Image URL')
                        ->url()
                        ->placeholder('https://... or PhysicsWallah student dashboard image URL')
                        ->columnSpanFull()
                        ->helperText('Paste any image URL (such as a PhysicsWallah dashboard banner, maritime graphic, or CDN link).'),

                    Textarea::make('description')
                        ->label('Batch Overview & Description')
                        ->rows(3)
                        ->placeholder('Comprehensive training batch covering theory, question banks, ladder problem sets, and test series.')
                        ->columnSpanFull(),

                    TagsInput::make('highlights')
                        ->label('Curriculum Highlights (Bullet points on card)')
                        ->placeholder('Add highlight (press Enter)...')
                        ->columnSpanFull()
                        ->helperText('Key highlights shown on the PhysicsWallah-style card, e.g. "Full Theory + Notes", "10,000+ Practice MCQs", "Mock Test Series Included".'),

                    TextInput::make('footer_tag')
                        ->label('Card Footer Promotional Tag')
                        ->placeholder('e.g. Free Test Series & Notes Included')
                        ->default('Free Test Series & Question Bank Included')
                        ->columnSpanFull(),
                ])->columns(2),

            Section::make('Pricing & WordPress Payment Integration')
                ->description('Set selling price, crossed-out MRP, and direct purchase link.')
                ->schema([
                    TextInput::make('price')
                        ->label('Selling Price (₹)')
                        ->numeric()
                        ->prefix('₹')
                        ->required()
                        ->default(2999)
                        ->helperText('Actual price charged to students.'),

                    TextInput::make('mrp_price')
                        ->label('Crossed-out MRP (₹)')
                        ->numeric()
                        ->prefix('₹')
                        ->default(5999)
                        ->helperText('Original strikethrough price shown on batch card.'),

                    TextInput::make('discount_percent')
                        ->label('Discount Badge (%)')
                        ->numeric()
                        ->suffix('%')
                        ->placeholder('e.g. 50')
                        ->helperText('Leave empty to auto-calculate from Selling Price & MRP.'),

                    TextInput::make('purchase_url')
                        ->label('Payment Link / Purchase URL')
                        ->url()
                        ->default('https://mymerchantnavy.com/pass/')
                        ->placeholder('https://mymerchantnavy.com/pass/')
                        ->columnSpanFull()
                        ->helperText('Direct URL student is redirected to when they click "Buy Now" / "Enroll in Batch".'),

                    TextInput::make('wp_product_id')
                        ->label('WordPress / WooCommerce Product ID')
                        ->maxLength(191)
                        ->helperText('Optional WordPress Product ID to link automated order processing.'),
                ])->columns(3),

            Section::make('Schedule, Validity & Visibility')
                ->description('Control when this batch is active and visible on the student LMS.')
                ->schema([
                    DatePicker::make('starts_at')
                        ->label('Batch Starts Date'),

                    TextInput::make('validity_text')
                        ->label('Validity Badge Text')
                        ->placeholder('e.g. Validity: Till Exam 2027 / 1 Year')
                        ->default('Validity: 1 Year / Till Exam 2027'),

                    DatePicker::make('ends_at')
                        ->label('Batch Expiration Date (Optional)'),

                    Select::make('status')
                        ->label('Batch Status')
                        ->options([
                            'active' => 'Active & Enrolling',
                            'upcoming' => 'Upcoming / Coming Soon',
                            'closed' => 'Enrollment Closed',
                        ])
                        ->default('active')
                        ->required(),

                    Toggle::make('is_live')
                        ->label('Put Live on Student LMS')
                        ->default(true)
                        ->inline(false)
                        ->helperText('When enabled, this batch is prominently displayed on the student LMS Batches store.'),
                ])->columns(3),

            Section::make('Included Curriculum & Tests')
                ->description('Select the LMS courses and mock tests that students unlock when enrolled in this batch.')
                ->schema([
                    Select::make('courses')
                        ->label('Included Courses')
                        ->relationship('courses', 'title')
                        ->multiple()
                        ->preload()
                        ->searchable()
                        ->helperText('Students with access to this batch will automatically have access to all selected courses.'),

                    Select::make('tests')
                        ->label('Included Mock Tests')
                        ->relationship('tests', 'title')
                        ->multiple()
                        ->preload()
                        ->searchable()
                        ->helperText('Full-length mock tests included in this batch.'),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Batch Title')
                    ->description(fn (Batch $record) => $record->batch_code ?? $record->slug)
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('target_exam')
                    ->label('Target Exam')
                    ->badge()
                    ->color('info')
                    ->searchable(),

                TextColumn::make('price')
                    ->label('Price')
                    ->formatStateUsing(function (Batch $record) {
                        $html = '<span class="font-bold text-emerald-600">₹' . number_format($record->price) . '</span>';
                        if ($record->mrp_price && $record->mrp_price > $record->price) {
                            $html .= ' <span class="line-through text-slate-400 text-xs">₹' . number_format($record->mrp_price) . '</span>';
                            $html .= ' <span class="text-xs bg-emerald-50 text-emerald-700 px-1.5 py-0.5 rounded font-semibold">' . $record->effective_discount . '% OFF</span>';
                        }
                        return $html;
                    })
                    ->html(),

                TextColumn::make('courses_count')
                    ->counts('courses')
                    ->label('Courses')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('tests_count')
                    ->counts('tests')
                    ->label('Tests')
                    ->badge()
                    ->color('gray'),

                ToggleColumn::make('is_live')
                    ->label('Live on LMS'),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'active' => 'success',
                        'upcoming' => 'warning',
                        'closed' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('target_exam')
                    ->label('Target Exam')
                    ->options([
                        'IMU-CET' => 'IMU-CET',
                        'DNS Sponsorship' => 'DNS Sponsorship',
                        'G.P. Rating' => 'G.P. Rating',
                        'B.Tech Marine' => 'B.Tech Marine',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBatches::route('/'),
            'create' => Pages\CreateBatch::route('/create'),
            'edit' => Pages\EditBatch::route('/{record}/edit'),
        ];
    }
}

