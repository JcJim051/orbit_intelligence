<?php

namespace App\Filament\Resources;

use App\Enums\UserRole;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Administración';

    protected static ?string $navigationLabel = 'Equipo y permisos';

    protected static ?string $modelLabel = 'usuario';

    protected static ?string $pluralModelLabel = 'Usuarios';

    protected static ?string $slug = 'usuarios';

    protected static ?int $navigationSort = 20;

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return auth()->user()?->canAccessPlatformAdministration() ?? false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Usuario')
                    ->searchable()
                    ->sortable()
                    ->description(fn (User $record): string => $record->is(auth()->user()) ? 'Tu cuenta' : ''),
                TextColumn::make('email')
                    ->label('Correo')
                    ->searchable()
                    ->copyable()
                    ->sortable(),
                TextColumn::make('role')
                    ->label('Rol')
                    ->badge()
                    ->formatStateUsing(fn (UserRole $state): string => $state->label())
                    ->color(fn (UserRole $state): string => match ($state) {
                        UserRole::Admin => 'danger',
                        UserRole::Manager => 'warning',
                        UserRole::ManagementSupport => 'info',
                        UserRole::SiidManager => 'success',
                        default => 'gray',
                    })
                    ->sortable(),
                IconColumn::make('active')
                    ->label('Activo')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label('Rol')
                    ->options(static::roleOptions())
                    ->searchable(),
                TernaryFilter::make('active')
                    ->label('Estado')
                    ->trueLabel('Activos')
                    ->falseLabel('Inactivos')
                    ->placeholder('Todos'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Crear usuario')
                    ->icon(Heroicon::OutlinedUserPlus)
                    ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false)
                    ->form(static::createFormSchema())
                    ->mutateDataUsing(function (array $data): array {
                        $data['password'] = Hash::make($data['password']);
                        unset($data['password_confirmation']);

                        return $data;
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->label('Editar')
                        ->icon(Heroicon::OutlinedPencilSquare)
                        ->visible(fn (User $record): bool => static::canEditRecord($record))
                        ->form(static::editFormSchema())
                        ->mutateDataUsing(fn (array $data, User $record): array => static::sanitizeEditData($data, $record)),
                    Action::make('password')
                        ->label('Restablecer contraseña')
                        ->icon(Heroicon::OutlinedKey)
                        ->visible(fn (User $record): bool => (auth()->user()?->isAdmin() ?? false) && ! $record->is(auth()->user()))
                        ->form(static::passwordFormSchema())
                        ->requiresConfirmation()
                        ->modalHeading(fn (User $record): string => 'Restablecer contraseña de '.$record->name)
                        ->action(function (array $data, User $record): void {
                            $record->update(['password' => Hash::make($data['password'])]);
                            $record->tokens()->delete();
                        }),
                    Action::make('deactivate')
                        ->label('Inactivar')
                        ->icon(Heroicon::OutlinedUserMinus)
                        ->color('danger')
                        ->visible(fn (User $record): bool => (auth()->user()?->isAdmin() ?? false) && ! $record->is(auth()->user()) && $record->active)
                        ->requiresConfirmation()
                        ->modalHeading(fn (User $record): string => 'Inactivar cuenta de '.$record->name)
                        ->action(function (User $record): void {
                            $record->update(['active' => false]);
                            $record->tokens()->delete();
                        }),
                    Action::make('activate')
                        ->label('Activar')
                        ->icon(Heroicon::OutlinedUserPlus)
                        ->color('success')
                        ->visible(fn (User $record): bool => (auth()->user()?->isAdmin() ?? false) && ! $record->active)
                        ->requiresConfirmation()
                        ->action(fn (User $record): bool => $record->update(['active' => true])),
                ]),
            ])
            ->defaultSort('name')
            ->persistSearchInSession()
            ->persistFiltersInSession()
            ->persistSortInSession()
            ->persistRecordsPerPageInSession()
            ->emptyStateHeading('No hay usuarios registrados')
            ->emptyStateDescription('Cree el primer usuario desde el botón superior.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
        ];
    }

    /** @return array<string, string> */
    private static function roleOptions(): array
    {
        return collect(UserRole::cases())
            ->mapWithKeys(fn (UserRole $role): array => [$role->value => $role->label()])
            ->all();
    }

    /** @return array<int, mixed> */
    private static function createFormSchema(): array
    {
        return [
            TextInput::make('name')
                ->label('Nombre completo')
                ->required()
                ->maxLength(120),
            TextInput::make('email')
                ->label('Correo institucional')
                ->email()
                ->required()
                ->maxLength(180)
                ->unique(ignoreRecord: true),
            Select::make('role')
                ->label('Rol')
                ->options(static::roleOptions())
                ->required()
                ->native(false),
            Toggle::make('active')
                ->label('Cuenta activa')
                ->default(true),
            TextInput::make('password')
                ->label('Contraseña inicial')
                ->password()
                ->revealable()
                ->required()
                ->minLength(12)
                ->confirmed(),
            TextInput::make('password_confirmation')
                ->label('Confirmar contraseña')
                ->password()
                ->revealable()
                ->required()
                ->dehydrated(false),
        ];
    }

    /** @return array<int, mixed> */
    private static function editFormSchema(): array
    {
        return [
            TextInput::make('name')
                ->label('Nombre completo')
                ->required()
                ->maxLength(120)
                ->disabled(fn (): bool => ! (auth()->user()?->isAdmin() ?? false)),
            TextInput::make('email')
                ->label('Correo institucional')
                ->email()
                ->required()
                ->maxLength(180)
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule): Unique => $rule)
                ->disabled(fn (): bool => ! (auth()->user()?->isAdmin() ?? false)),
            Select::make('role')
                ->label('Rol')
                ->options(fn (): array => auth()->user()?->isAdmin() ? static::roleOptions() : [
                    UserRole::Member->value => UserRole::Member->label(),
                    UserRole::SiidManager->value => UserRole::SiidManager->label(),
                ])
                ->required()
                ->native(false)
                ->disabled(fn (?User $record): bool => ! $record || $record->is(auth()->user()) || ! static::canModifyFunctionalRole($record)),
            Toggle::make('active')
                ->label('Cuenta activa')
                ->disabled(fn (?User $record): bool => ! $record || $record->is(auth()->user())),
        ];
    }

    /** @return array<int, mixed> */
    private static function passwordFormSchema(): array
    {
        return [
            TextInput::make('password')
                ->label('Nueva contraseña')
                ->password()
                ->revealable()
                ->required()
                ->minLength(12)
                ->confirmed(),
            TextInput::make('password_confirmation')
                ->label('Confirmar nueva contraseña')
                ->password()
                ->revealable()
                ->required()
                ->dehydrated(false),
        ];
    }

    private static function canEditRecord(User $record): bool
    {
        return (auth()->user()?->isAdmin() ?? false) || static::canModifyFunctionalRole($record);
    }

    private static function canModifyFunctionalRole(User $record): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->isAdmin()
            || (
                $user->role === UserRole::Manager
                && in_array($record->role, [UserRole::Member, UserRole::SiidManager], true)
            );
    }

    /** @param array<string, mixed> $data */
    private static function sanitizeEditData(array $data, User $record): array
    {
        $user = auth()->user();

        if (! $user?->isAdmin()) {
            $data['name'] = $record->name;
            $data['email'] = $record->email;
            $data['active'] = $record->active;
        }

        if ($record->is($user)) {
            $data['role'] = UserRole::Admin->value;
            $data['active'] = true;
        }

        return $data;
    }
}
