<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EnrollmentResource\Pages\ListEnrollments;
use App\Models\Enrollment;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Sales > Purchases: who bought which course, when, what they paid and until when it is valid (read-only).
 * This is the page to check when a student says "I paid but cannot see my course".
 */
class EnrollmentResource extends Resource
{
    protected static ?string $model = Enrollment::class;

    protected static ?string $navigationLabel = 'Purchases';

    protected static ?string $modelLabel = 'purchase';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';

    protected static string|\UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['user', 'course']))
            ->columns([
                TextColumn::make('purchase_date')->label('Purchased on')->dateTime('d M Y, h:i A')->sortable()
                    ->description(fn (Enrollment $r) => $r->purchase_date?->diffForHumans()),
                TextColumn::make('user.name')->label('Student')->searchable()->placeholder('—')
                    ->description(fn (Enrollment $r) => $r->user?->email),
                TextColumn::make('user.phone')->label('Mobile')->searchable()->placeholder('—'),
                TextColumn::make('course.title')->label('Course')->sortable()->wrap(),
                TextColumn::make('amount')->label('Paid')->money('INR')->placeholder('—')->sortable()
                    ->description(fn (Enrollment $r) => $r->coupon_code ? "Coupon {$r->coupon_code}" : null),
                TextColumn::make('purchase_type')->label('Type')->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) { 'paid' => 'Paid', 'free' => 'Free', default => 'Older entry' })
                    ->color(fn (?string $state) => match ($state) { 'paid' => 'success', 'free' => 'info', default => 'gray' }),
                TextColumn::make('expires_at')->label('Valid until')->dateTime('d M Y')->placeholder('Lifetime')->sortable()
                    ->color(fn (Enrollment $r) => $r->isExpired() ? 'danger' : null),
                TextColumn::make('order_id')->label('Order ID')->searchable()->copyable()->placeholder('—')->toggleable(),
                TextColumn::make('payment_id')->label('Payment ID')->searchable()->copyable()->placeholder('—')->toggleable(),
                TextColumn::make('purchase_email_sent_at')->label('Emails sent')->since()->placeholder('Not sent')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('purchase_date', 'desc')
            ->filters([
                SelectFilter::make('course_id')->label('Course')->relationship('course', 'title'),
                SelectFilter::make('purchase_type')->label('Type')->options(['paid' => 'Paid', 'free' => 'Free', 'assigned' => 'Older entry']),
            ])
            ->emptyStateHeading('No purchases yet')
            ->emptyStateDescription('Purchases appear here as soon as a student pays.');
    }

    public static function getPages(): array
    {
        return ['index' => ListEnrollments::route('/')];
    }
}
