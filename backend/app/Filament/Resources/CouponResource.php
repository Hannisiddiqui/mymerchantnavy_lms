<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CouponResource\Pages;
use App\Models\Course;
use App\Models\Coupon;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Discount coupons (Super Admin). When a student pays, WordPress asks the LMS for the price after the coupon
 * and creates the Razorpay order for that amount, so the discount really changes what Razorpay charges.
 */
class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-ticket';

    protected static string|\UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Coupons';

    public static function canViewAny(): bool
    {
        return auth('admin')->user()?->isSuperAdmin() ?? false;
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Coupon')->schema([
                TextInput::make('code')->required()->maxLength(40)->unique(ignoreRecord: true)
                    ->helperText('Letters, numbers, - and _ only. Students type it at checkout (capital letters are applied automatically).')
                    ->dehydrateStateUsing(fn ($state) => strtoupper(trim((string) $state)))
                    ->suffixAction(\Filament\Actions\Action::make('generate')->icon('heroicon-o-sparkles')->tooltip('Generate a code')
                        ->action(fn ($set) => $set('code', 'MMN'.strtoupper(\Illuminate\Support\Str::random(6))))),
                TextInput::make('description')->label('Note for your team')->maxLength(255)->placeholder('e.g. Diwali offer 2026'),
                Toggle::make('is_active')->label('Active')->default(true)->inline(false),
            ])->columns(3),

            Section::make('Discount')->schema([
                Select::make('type')->label('Type')->options(['percent' => 'Percentage (%)', 'fixed' => 'Fixed amount (₹)'])
                    ->default('percent')->required()->live(),
                TextInput::make('value')->label(fn ($get) => $get('type') === 'fixed' ? 'Amount off (₹)' : 'Percent off')->numeric()->required()->minValue(1)
                    ->maxValue(fn ($get) => $get('type') === 'fixed' ? 1000000 : 100),
                TextInput::make('max_discount')->label('Maximum discount (₹)')->numeric()->minValue(1)
                    ->helperText('Optional cap, useful for percentage coupons.')
                    ->visible(fn ($get) => $get('type') !== 'fixed'),
                TextInput::make('min_amount')->label('Minimum course price (₹)')->numeric()->minValue(1)->helperText('Optional. The coupon only works on courses that cost at least this.'),
            ])->columns(2),

            Section::make('Where and how long it works')->schema([
                TagsInput::make('product_ids')->label('Only for these course product IDs')
                    ->suggestions(fn () => Course::whereNotNull('wordpress_product_id')->pluck('wordpress_product_id')
                        ->flatMap(fn ($v) => array_map('trim', explode(',', $v)))->unique()->values()->all())
                    ->helperText('Leave empty to allow every course. The product ID is the one set on the course (WordPress product ID).')
                    ->columnSpanFull(),
                TextInput::make('usage_limit')->label('Total uses allowed')->numeric()->minValue(1)->helperText('Empty = unlimited.'),
                TextInput::make('per_student_limit')->label('Uses per student')->numeric()->minValue(1)->default(1)->required(),
                DateTimePicker::make('starts_at')->label('Starts on')->seconds(false),
                DateTimePicker::make('expires_at')->label('Ends on')->seconds(false)->after('starts_at'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->weight('bold')->copyable()->searchable(),
                TextColumn::make('discount')->label('Discount')->state(fn (Coupon $c) => $c->type === 'fixed' ? '₹'.number_format((float) $c->value, 0).' off' : rtrim(rtrim((string) $c->value, '0'), '.').'% off'),
                TextColumn::make('status')->label('Status')->badge()->state(fn (Coupon $c) => $c->statusLabel())
                    ->color(fn (string $state) => match ($state) { 'Live' => 'success', 'Scheduled' => 'info', 'Paused' => 'gray', default => 'danger' }),
                TextColumn::make('used_count')->label('Used')->state(fn (Coupon $c) => $c->used_count.($c->usage_limit ? " / {$c->usage_limit}" : '')),
                TextColumn::make('expires_at')->label('Ends')->dateTime('d M Y')->placeholder('No end date')->sortable(),
                TextColumn::make('description')->label('Note')->limit(40)->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->emptyStateHeading('No coupons yet')
            ->emptyStateDescription('Create a coupon and students can use it at checkout.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCoupons::route('/'),
            'create' => Pages\CreateCoupon::route('/create'),
            'edit' => Pages\EditCoupon::route('/{record}/edit'),
        ];
    }
}
