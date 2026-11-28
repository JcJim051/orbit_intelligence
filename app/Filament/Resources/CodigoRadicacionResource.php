<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CodigoRadicacionResource\Pages\ListCodigosRadicacion;
use App\Models\Dependencia;
use App\Services\Bancos\RadicacionBanco;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class CodigoRadicacionResource extends Resource
{
    protected static ?string $model = Dependencia::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = 'Bancos de proyectos';

    protected static ?string $navigationLabel = 'Códigos de radicación';

    protected static ?string $modelLabel = 'código de radicación';

    protected static ?string $pluralModelLabel = 'Códigos de radicación';

    protected static ?string $slug = 'codigos-radicacion';

    protected static ?int $navigationSort = 20;

    public static function canAccess(): bool
    {
        return auth()->user()?->puedeAsignarCodigoRadicacion() ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->orderBy('nombre');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('codigo')->label('Código institucional')->searchable(),
                TextColumn::make('nombre')->label('Dependencia')->searchable(),
                TextColumn::make('codigo_radicacion')->label('Código de radicación')->placeholder('Sin código')->searchable(),
                TextColumn::make('activo')->label('Activa')->badge()->formatStateUsing(fn (bool $state): string => $state ? 'Sí' : 'No'),
            ])
            ->recordActions([
                Action::make('asignar')
                    ->label('Asignar código')
                    ->authorize(fn (): bool => auth()->user()?->puedeAsignarCodigoRadicacion() ?? false)
                    ->fillForm(fn (Dependencia $record): array => [
                        'codigo_radicacion' => $record->codigo_radicacion,
                    ])
                    ->schema([
                        TextInput::make('codigo_radicacion')
                            ->label('Código que escribirá la secretaría')
                            ->maxLength(20)
                            ->helperText('Entre 4 y 20 caracteres: letras, números o guiones. Déjelo vacío para retirarlo.'),
                    ])
                    ->action(function (Dependencia $record, array $data): void {
                        app(RadicacionBanco::class)->guardarCodigo($record, $data['codigo_radicacion'] ?? null);
                        Notification::make()->title('Código de radicación guardado')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCodigosRadicacion::route('/'),
        ];
    }
}
