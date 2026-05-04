<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers\RolesRelationManager;
use App\Models\Setting;
use App\Models\User;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use BezhanSalleh\FilamentShield\Traits\HasShieldFormComponents;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Spatie\Permission\Models\Role;
use STS\FilamentImpersonate\Facades\Impersonation;

/**
 * Class UserResource
 *
 * Filament resource for managing users with role-based access, validation,
 * and impersonation support.
 */
class UserResource extends Resource implements HasShieldPermissions
{
    use HasShieldFormComponents;

    protected static function superAdminRoleName(): string
    {
        return User::superAdminRoleName();
    }

    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'create',
            'update',
            'delete',
            'delete_any',
            'restore',
            'restore_any',
            'force_delete',
            'force_delete_any',
        ];
    }

    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user';

    protected static string|\UnitEnum|null $navigationGroup = 'Users';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', User::class);
    }

    public static function form(Schema $schema): Schema
    {
        // Check if both phone and email are required at registration
        $requireBoth = Setting::getBooleanValue('features.phone_email_at_registration', false);

        return $schema
            ->schema([
                TextInput::make('name')
                    ->label(__('message.name'))
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->dehydrateStateUsing(fn ($state) => strtolower($state)),

                TextInput::make('email')
                    ->label(__('message.email'))
                    ->email()
                    ->required(
                        fn (Get $get) => $requireBoth
                            ? true
                            : blank($get('phone')) // require if phone is empty
                    )
                    ->maxLength(255)
                    ->reactive()
                    ->unique(ignoreRecord: true)
                    ->dehydrateStateUsing(fn ($state) => strtolower($state)),

                TextInput::make('phone')
                    ->label(__('message.phone'))
                    ->tel()
                    ->required(
                        fn (Get $get) => $requireBoth
                            ? true
                            : blank($get('email')) // require if email is empty
                    )
                    ->maxLength(20)
                    ->reactive()
                    ->unique(ignoreRecord: true)
                    ->dehydrateStateUsing(fn ($state) => strtolower($state)),

                Section::make()
                    ->schema([
                        Grid::make()
                            ->schema([
                                TextInput::make('password')
                                    ->label(__('message.password'))
                                    ->password()
                                    ->required(fn ($record) => ! $record || ! $record->exists)
                                    ->confirmed() // ensures it matches confirm_password
                                    ->dehydrateStateUsing(fn ($state) => bcrypt($state))
                                    ->maxLength(255),

                                TextInput::make('password_confirmation')
                                    ->label(__('message.confirm_password'))
                                    ->password()
                                    ->required(fn ($record) => ! $record || ! $record->exists)
                                    ->dehydrated(false), // don't save this field to the database
                            ]),
                    ]),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('message.name'))
                    ->weight('font-medium')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('email')
                    ->label(__('message.email'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('phone')
                    ->label(__('message.phone'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label(__('message.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->searchable(),
                TextColumn::make('updated_at')
                    ->label(__('message.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->searchable(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                SelectFilter::make('role')
                    ->label(__('message.roles'))
                    ->placeholder('All Roles')
                    ->options(function () {
                        $allRoles = Role::pluck('name', 'name')->toArray();
                        $rolesWithNoRole = ['__no_role__' => 'No Role'];
                        $user = auth()->user();
                        $visibleRoles = collect();

                        if ($user->can('viewAdminRole', User::class)) {
                            $superAdminRole = static::superAdminRoleName();

                            if (isset($allRoles[$superAdminRole])) {
                                $visibleRoles->put($superAdminRole, $allRoles[$superAdminRole]);
                            }
                        }

                        if ($user->can('viewStaffRole', User::class)) {
                            $staffRoles = collect($allRoles)
                                ->reject(fn ($name, $key) => in_array(strtolower($key), [strtolower(static::superAdminRoleName()), 'user']));
                            $visibleRoles = $visibleRoles->merge($staffRoles);
                        }

                        if ($user->can('viewNoRole', User::class)) {
                            if ($visibleRoles->isEmpty()) {

                                return $rolesWithNoRole;
                            }

                            $visibleRoles = collect($rolesWithNoRole)->merge($visibleRoles);
                        }

                        if ($user->can('viewUserRole', User::class)) {
                            if (isset($allRoles['user'])) {
                                $visibleRoles->put('user', $allRoles['user']);
                            }
                        }

                        return $visibleRoles->isEmpty()
                            ? []
                            : $visibleRoles->toArray();
                    })
                    ->default(null)  // “All Roles” (no filtering) by default
                    ->query(function (Builder $query, array $data) {
                        if (empty($data['value'])) {
                            return $query; // “All Roles” chosen
                        }

                        if ($data['value'] === '__no_role__') {
                            // Filter users who have NO roles
                            return $query->whereDoesntHave('roles');
                        }

                        return $query->whereHas('roles', fn ($q) => $q->where('name', $data['value']));
                    }),

            ], layout: FiltersLayout::AboveContent)->filtersFormColumns(2)
            ->recordActions([

                Action::make('impersonate')
                    ->label('')
                    ->size(14)
                    ->icon('heroicon-o-user')
                    ->color('danger')
                    ->tooltip('User Impersonation: you cant return to admin panel')
                    ->action(function ($record) {
                        session()->put('impersonator_id', auth()->id());
                        Impersonation::enter(auth()->user(), $record);

                        return redirect('/'); // or your intended redirect path after impersonation
                    })
                    ->visible(function ($record) {
                        $user = auth()->user();

                        return $user->can('impersonate', User::class) &&
                            $record->isStandardUser() &&
                            (! ($record->phone && ! $record->hasVerifiedPhone())) &&
                            (! ($record->email && ! $record->hasVerifiedEmail()));
                    }),

                // Disabled impersonate icon shown when verification is missing
                Action::make('impersonate-disabled')
                    ->label('')
                    ->size(14)
                    ->icon('heroicon-o-user-minus')
                    ->tooltip('Impersonation disabled: user not verified')
                    ->color('gray')
                    ->visible(function ($record) {
                        $user = auth()->user();

                        return $user->can('impersonate', User::class) &&
                            $record->isStandardUser() &&
                            (
                                ($record->phone && ! $record->hasVerifiedPhone()) ||
                                ($record->email && ! $record->hasVerifiedEmail())
                            );
                    }),
                ViewAction::make()->icon('heroicon-o-eye')
                    ->color('primary')
                    ->label('')->visible(fn ($record) => auth()->user()?->can('view', $record)),
                EditAction::make()->icon('heroicon-o-pencil')
                    ->label('')
                    ->visible(fn ($record) => auth()->user()?->can('update', $record)),
                DeleteAction::make()
                    ->icon('heroicon-o-trash')
                    ->color('warning')
                    ->label('')
                    ->visible(function ($record) {
                        $user = auth()->user();

                        if (! $user->can('delete', $record)) {
                            return false;
                        }

                        if (! $record->isSuperAdmin()) {
                            return true;
                        }

                        return ! $record->isSuperAdmin();
                    })
                    ->modalHeading('Trash User')
                    ->modalDescription('Are you sure you want to temporarily delete this user?')
                    ->modalSubmitActionLabel('Yes, trash user'),

                RestoreAction::make()->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->label(''),

                ForceDeleteAction::make()
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->label('')
                    ->visible(fn ($record) => auth()->user()?->can('forceDelete', $record))
                    ->modalHeading('Permanently Delete User')
                    ->modalDescription('This action cannot be undone. Are you sure you want to permanently delete this user?')
                    ->modalSubmitActionLabel('Yes, delete permanently')
                    ->visible(function ($record) {
                        $user = auth()->user();

                        if (! $user->can('delete', $record)) {
                            return false;
                        }

                        if (! $record->isSuperAdmin()) {
                            return true;
                        }

                        return ! $record->isSuperAdmin();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->color('success')
                        ->icon('heroicon-o-trash')
                        ->visible(fn () => auth()->user()?->can('deleteAny', User::class))
                        ->action(function ($records) {
                            if ($records->contains(fn ($user) => $user->isSuperAdmin())) {
                                Notification::make()
                                    ->title('Error')
                                    ->body('Cannot delete users with the super admin role.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $records->each->delete();
                        }),

                    ForceDeleteBulkAction::make()
                        ->visible(fn ($records) => auth()->user()?->can('forceDeleteAny', User::class))
                        ->action(function ($records) {
                            if ($records->contains(fn ($user) => $user->isSuperAdmin())) {
                                Notification::make()
                                    ->title('Error')
                                    ->body('Cannot delete users with the super admin role.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $records->each->delete();
                        }),
                    RestoreBulkAction::make()
                        ->visible(fn ($records) => auth()->user()?->can('restoreAny', User::class)),
                ]),

            ]);
    }

    public static function getRelations(): array
    {
        return [
            RolesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
        $user = auth()->user();

        if ($user->can('viewAdminRole', User::class)) {
            return $query;
        }

        if ($user->can('viewStaffRole', User::class)) {
            $query->whereDoesntHave('roles', fn ($q) => $q->where('name', static::superAdminRoleName()));

            if (! $user->can('viewNoRole', User::class)) {
                $query->whereHas('roles');
            }

            return $query;
        }

        // If the user has neither permission, only show users with "user" role
        return $query->whereHas('roles', fn ($q) => $q->where('name', 'user'));
    }
}
