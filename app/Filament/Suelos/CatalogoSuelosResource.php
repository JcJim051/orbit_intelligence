<?php

namespace App\Filament\Suelos;

use App\Services\Suelos\CatalogoExcel;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

abstract class CatalogoSuelosResource extends Resource
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Inteligencia geográfica';

    /**
     * @var list<array{name: string, label: string, tipo: string, excel?: string, modelo?: class-string<Model>}>
     */
    protected static array $columnas = [];

    public static function form(Schema $schema): Schema
    {
        return $schema->components(static::campos());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns(static::columnasTabla())
            ->headerActions([
                CreateAction::make()->label('Nuevo registro'),
                Action::make('descargarTodo')->label('Descargar todo')->icon(Heroicon::OutlinedArrowDownTray)
                    ->url(fn (): string => route('inteligencia.catalogos.exportar', ['catalogo' => static::clave()]))
                    ->visible(fn (): bool => auth()->user()?->can('export', static::getModel()) ?? false),
                Action::make('plantilla')->label('Descargar plantilla')->icon(Heroicon::OutlinedDocumentArrowDown)
                    ->url(fn (): string => route('inteligencia.catalogos.plantilla', ['catalogo' => static::clave()]))
                    ->visible(fn (): bool => auth()->user()?->can('export', static::getModel()) ?? false),
                Action::make('importar')->label('Importar desde Excel')->icon(Heroicon::OutlinedArrowUpTray)
                    ->visible(fn (): bool => auth()->user()?->can('import', static::getModel()) ?? false)
                    ->authorize(fn (): bool => auth()->user()?->can('import', static::getModel()) ?? false)
                    ->schema([
                        FileUpload::make('archivo')->label('Archivo de Excel')->required()->disk('local')->directory('imports')->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ]),
                    ])
                    ->action(function (array $data): void {
                        $ruta = Storage::disk('local')->path($data['archivo']);
                        app(CatalogoExcel::class)->importar(static::getModel(), static::$columnas, $ruta);
                        Storage::disk('local')->delete($data['archivo']);
                        Notification::make()->title('Catálogo importado')->success()->send();
                    }),
            ])
            ->recordActions([
                EditAction::make()->label('Editar'),
                DeleteAction::make()->label('Eliminar'),
            ])
            ->defaultSort('codigo');
    }

    public static function getPages(): array
    {
        return [
            'index' => static::paginaListado()::route('/'),
        ];
    }

    abstract public static function clave(): string;

    /**
     * @return class-string
     */
    abstract public static function paginaListado(): string;

    /**
     * @return list<array{name: string, label: string, tipo: string, excel?: string, modelo?: class-string<Model>}>
     */
    public static function definicionColumnas(): array
    {
        return static::$columnas;
    }

    /**
     * @return list<Component>
     */
    protected static function campos(): array
    {
        return array_map(function (array $columna): Component {
            $campo = match ($columna['tipo']) {
                'texto_largo' => Textarea::make($columna['name']),
                'bool' => Toggle::make($columna['name'])->default(true),
                'numero' => TextInput::make($columna['name'])->numeric(),
                'relacion' => Select::make($columna['name'])->options(fn (): array => ($columna['modelo'])::query()->orderBy('codigo')->get()->mapWithKeys(fn (Model $registro): array => [$registro->getKey() => $registro->etiqueta()])->all())->searchable(),
                'enum' => Select::make($columna['name'])->options(array_combine($columna['opciones'], $columna['opciones'])),
                default => TextInput::make($columna['name']),
            };

            return $campo->label($columna['label'])->required(in_array($columna['name'], ['codigo', 'nombre'], true) || ($columna['requerido'] ?? false));
        }, static::$columnas);
    }

    /**
     * @return list<TextColumn|IconColumn>
     */
    protected static function columnasTabla(): array
    {
        $columnas = [
            TextColumn::make('codigo')->label('Registro')->formatStateUsing(fn (Model $record): string => $record->etiqueta())->searchable(),
        ];

        foreach (static::$columnas as $columna) {
            if (in_array($columna['name'], ['codigo', 'nombre'], true)) {
                continue;
            }

            if ($columna['tipo'] === 'bool') {
                $columnas[] = IconColumn::make($columna['name'])->label($columna['label'])->boolean();

                continue;
            }

            $columnas[] = TextColumn::make($columna['name'])->label($columna['label'])->placeholder('—');
        }

        return $columnas;
    }
}
