<?php

namespace App\Filament\Resources;

use App\Enums\AdminRole;
use App\Filament\Resources\AdminResource\Pages;
use App\Models\Admin;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** People who can log in to this admin panel. Super Admin has highest authority. */
class AdminResource extends Resource
{
    protected static ?string $model = Admin::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Admins';

    public static function canViewAny(): bool
    {
        return auth('admin')->user()?->isSuperAdmin() ?? false;
    }

    public static function canCreate(): bool
    {
        return auth('admin')->user()?->isSuperAdmin() ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        /** @var Admin $record */
        $currentUser = auth('admin')->user();
        if (! $currentUser) {
            return false;
        }

        // Super Admin can edit any admin
        if ($currentUser->isSuperAdmin()) {
            return true;
        }

        // Regular admin can only edit their own profile, never the Super Admin or other admins
        return ! $record->isSuperAdmin() && $record->is($currentUser);
    }

    public static function canDelete(Model $record): bool
    {
        /** @var Admin $record */
        $currentUser = auth('admin')->user();
        if (! $currentUser || ! $currentUser->isSuperAdmin()) {
            return false;
        }

        return ! $record->isSuperAdmin() && ! $record->is($currentUser);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required(),
            TextInput::make('email')->email()->required()->unique(ignoreRecord: true)
                ->disabled(fn (?Admin $record) => $record?->isSuperAdmin())
                ->helperText(fn (?Admin $record) => $record?->isSuperAdmin() ? 'Super Admin email is fixed and cannot be changed.' : null),
            Select::make('role')
                ->label('Admin Role')
                ->options(function (?Admin $record) {
                    if ($record?->isSuperAdmin()) {
                        return [
                            AdminRole::SuperAdmin->value => 'Super Admin (Highest Authority)',
                        ];
                    }

                    return [
                        AdminRole::Admin->value => 'Admin (Manage courses, tests, reports)',
                        AdminRole::SiteManager->value => 'Site Manager (Student Support & WhatsApp Chat Manager)',
                        AdminRole::Manager->value => 'Manager (Content and question editor)',
                        AdminRole::Support->value => 'Support (Student assistance)',
                    ];
                })
                ->default(AdminRole::Admin->value)
                ->required()
                ->disabled(function (?Admin $record) {
                    $currentUser = auth('admin')->user();
                    if (! $currentUser?->isSuperAdmin()) {
                        return true;
                    }

                    return $record?->isSuperAdmin();
                })
                ->helperText(function (?Admin $record) {
                    if ($record?->isSuperAdmin()) {
                        return 'Super Admin is the highest role, permanently held by '.Admin::SUPER_ADMIN_EMAIL;
                    }
                    if (! auth('admin')->user()?->isSuperAdmin()) {
                        return 'Only the Super Admin ('.Admin::SUPER_ADMIN_EMAIL.') has power to assign roles to other admins.';
                    }

                    return 'Only Super Admin can assign or change roles for admins.';
                }),
            TextInput::make('password')->password()->revealable()
                ->required(fn (string $operation) => $operation === 'create')
                ->minLength(8)
                ->dehydrated(fn (?string $state) => filled($state))
                ->helperText(fn (string $operation) => $operation === 'edit' ? 'Leave empty to keep the current password.' : null),

            Section::make('Granular Permissions Control (Super Admin Exclusive)')
                ->description('Check or uncheck exact access capabilities for this administrator.')
                ->visible(fn () => auth('admin')->user()?->isSuperAdmin())
                ->schema([
                    CheckboxList::make('permissions')
                        ->label('Assigned Permissions')
                        ->options([
                            'manage_courses' => 'Courses & Curriculum (Create, edit, organize courses)',
                            'manage_tests' => 'Tests & Question Bank (Create & edit mock tests)',
                            'manage_batches' => 'Batches & Store (Create and put batches live)',
                            'manage_page_builder' => 'Page Builder (Design & publish custom student pages)',
                            'manage_students' => 'Students & Enrollments (Inspect students, manual enrollment)',
                            'manage_reports' => 'Question Reports (Review and fix reported questions)',
                            'manage_support_chat' => 'Support Chat & WhatsApp Queries (Direct student reply & issue management)',
                            'can_publish_without_approval' => 'Direct Publish / Auto-Live (Changes publish immediately without Super Admin approval)',
                            'receive_change_emails' => 'Receive Change Notification Emails',
                        ])
                        ->columns(2)
                        ->helperText('Super Admin always holds full permissions. For all other admins, select which modules and powers they have access to.'),
                ])->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->weight('bold'),
                TextColumn::make('email')->searchable(),
                TextColumn::make('role')
                    ->label('Role')
                    ->badge()
                    ->formatStateUsing(function ($state) {
                        if ($state instanceof AdminRole) {
                            return $state->label();
                        }
                        $role = AdminRole::tryFrom((string) $state);

                        return $role ? $role->label() : ucfirst((string) $state);
                    })
                    ->color(function ($state) {
                        $val = $state instanceof AdminRole ? $state->value : (string) $state;

                        return match ($val) {
                            AdminRole::SuperAdmin->value => 'danger',
                            AdminRole::Admin->value => 'primary',
                            AdminRole::Manager->value => 'warning',
                            default => 'gray',
                        };
                    }),
                TextColumn::make('created_at')->label('Added')->dateTime('d M Y'),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (Admin $record) => static::canEdit($record)),
                DeleteAction::make()
                    ->hidden(fn (Admin $record) => ! static::canDelete($record)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdmins::route('/'),
            'create' => Pages\CreateAdmin::route('/create'),
            'edit' => Pages\EditAdmin::route('/{record}/edit'),
        ];
    }
}
