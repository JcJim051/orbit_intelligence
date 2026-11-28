<?php

namespace App\Filament\Pages;

use App\Contracts\Micrositio\FuenteDatosMapaInicio;
use App\Models\MicrositioMapa;
use App\Models\MicrositioMapaCapa;
use App\Services\Micrositio\Mapa\SincronizarCapasMapaInicio;
use App\Services\Micrositio\PublicadorMicrositio;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use UnitEnum;

class MapaDelInicio extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static string|UnitEnum|null $navigationGroup = 'Micrositio GIEE';

    protected static ?string $navigationLabel = 'Mapa del inicio';

    protected static ?string $title = 'Mapa del inicio';

    protected static ?string $slug = 'mapa-del-inicio';

    protected static ?int $navigationSort = 25;

    protected string $view = 'filament.pages.mapa-del-inicio';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public int $vista = 0;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(): void
    {
        $registro = MicrositioMapa::query()->first();
        $estado = $registro?->atributosPublicables() ?? [
            'mostrar_bloque' => true,
            'capa_principal' => 'POB-2026',
            'capas_adicionales' => ['PETROLEO', 'AGRO-PROD'],
            'periodo_modo' => 'ultimo',
            'paleta' => 'auto',
            'polaridad' => 'auto',
            'texto_tarjeta' => '29 municipios · capital Villavicencio',
            'texto_cta' => 'Explorar en el visor',
        ];
        $disponibles = array_keys($this->opcionesCapas());
        if (! in_array($estado['capa_principal'] ?? null, $disponibles, true)) {
            $estado['capa_principal'] = in_array('POB-2026', $disponibles, true) ? 'POB-2026' : ($disponibles[0] ?? null);
        }
        $estado['capas_adicionales'] = array_values(array_filter(
            (array) ($estado['capas_adicionales'] ?? ['PETROLEO', 'AGRO-PROD']),
            fn (mixed $codigo): bool => is_string($codigo) && in_array($codigo, $disponibles, true) && $codigo !== $estado['capa_principal'],
        ));
        if ($estado['capas_adicionales'] === []) {
            $estado['capas_adicionales'] = array_values(array_filter(
                ['PETROLEO', 'AGRO-PROD'],
                fn (string $codigo): bool => in_array($codigo, $disponibles, true) && $codigo !== $estado['capa_principal'],
            ));
        }
        $this->editorForm->fill($estado);
    }

    public function editorForm(Schema $schema): Schema
    {
        return $schema->components([
            Toggle::make('mostrar_bloque')
                ->label('Mostrar bloque del mapa')
                ->helperText('Si se apaga, el inicio muestra solo el texto y las cifras.'),
            Select::make('capa_principal')
                ->label('Capa principal')
                ->options(fn (): array => $this->opcionesCapas())
                ->required()
                ->live()
                ->native(false)
                ->helperText('Es la capa que se ve al abrir el inicio.'),
            CheckboxList::make('capas_adicionales')
                ->label('Capas adicionales')
                ->options(fn (): array => $this->opcionesCapas())
                ->disableOptionWhen(fn (string $value, Get $get): bool => $value === $get('capa_principal'))
                ->columns(1)
                ->helperText('Máximo 3. Se muestran como píldoras sobre el mapa.')
                ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                    $codigos = array_values(array_filter((array) $value, fn (mixed $codigo): bool => is_string($codigo) && $codigo !== ''));

                    if (in_array($get('capa_principal'), $codigos, true)) {
                        $fail('La capa principal no puede estar entre las capas adicionales.');
                    }

                    if (count($codigos) > 3) {
                        $fail('Puede elegir máximo 3 capas adicionales.');
                    }
                }),
            Radio::make('periodo_modo')
                ->label('Periodo')
                ->options(['ultimo' => 'Último disponible', 'fijo' => 'Fijo'])
                ->required()
                ->live()
                ->helperText('Fase 1: cada capa trae un solo periodo en el JSON.'),
            Select::make('periodo_fijo')
                ->label('Periodo fijo')
                ->options(fn (Get $get): array => $this->periodos((string) $get('capa_principal')))
                ->native(false)
                ->visible(fn (Get $get): bool => $get('periodo_modo') === 'fijo')
                ->required(fn (Get $get): bool => $get('periodo_modo') === 'fijo'),
            Radio::make('paleta')
                ->label('Paleta de colores')
                ->options([
                    'auto' => 'Automática',
                    'azul' => 'Azul institucional',
                    'verde' => 'Verde',
                    'semaforo' => 'Semáforo',
                ])
                ->descriptions([
                    'azul' => 'De claro a #0943b5',
                    'verde' => 'De claro a #006b38',
                    'semaforo' => 'Rojo, naranja, amarillo, verde claro y verde',
                ])
                ->required(),
            Radio::make('polaridad')
                ->label('Polaridad')
                ->options([
                    'auto' => 'Según la capa',
                    'alto_bueno' => 'Valor alto = favorable',
                    'alto_malo' => 'Valor alto = desfavorable',
                ])
                ->helperText('Con la paleta semáforo, «desfavorable» invierte los colores (alto = rojo).')
                ->required(),
            TextInput::make('texto_tarjeta')
                ->label('Texto de la tarjeta «Meta»')
                ->required()
                ->maxLength(160),
            TextInput::make('texto_cta')
                ->label('Texto del botón')
                ->required()
                ->maxLength(60),
            TextInput::make('url_cta')
                ->label('Enlace del botón')
                ->maxLength(500)
                ->helperText('Ruta relativa o URL https. Si se deja vacío, apunta al visor publicado.'),
            TextInput::make('url_ficha_tecnica')
                ->label('Ficha técnica del indicador')
                ->maxLength(500)
                ->helperText('Plantilla opcional. Use {codigo} para el código de la capa.'),
            TextInput::make('url_indicadores_municipio')
                ->label('Indicadores del municipio')
                ->maxLength(500)
                ->helperText('Plantilla opcional. Use {divipola} para el código del municipio.'),
        ])->statePath('data');
    }

    public function guardar(): void
    {
        $datos = $this->editorForm->getState();
        $datos['capas_adicionales'] = array_values(array_filter(
            (array) ($datos['capas_adicionales'] ?? []),
            fn (mixed $codigo): bool => is_string($codigo) && $codigo !== '' && $codigo !== ($datos['capa_principal'] ?? null),
        ));

        $registro = MicrositioMapa::query()->first() ?? new MicrositioMapa;
        $registro->fill($datos)->save();
        $this->vista++;

        Notification::make()->title('Borrador del mapa guardado.')->success()->send();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(MicrositioMapaCapa::query()->with('ultimaSincronizacion')->orderBy('orden')->orderBy('id'))
            ->columns([
                TextColumn::make('rotulo_corto')->label('Capa')->searchable(),
                TextColumn::make('fuente')->label('Fuente')->limit(42)->tooltip(fn (MicrositioMapaCapa $record): string => $record->fuente),
                TextColumn::make('periodo')->label('Periodo')->badge(),
                TextColumn::make('fuente_actualizada_en')->label('Fuente actualizada')->dateTime('d/m/Y H:i')->placeholder('No informada'),
                TextColumn::make('sincronizada_en')->label('Sincronización')->since()->placeholder('Inicial'),
                TextColumn::make('ultimaSincronizacion.registros_procesados')->label('Registros')->numeric()->placeholder('—'),
                TextColumn::make('estado')->label('Estado')->badge()->color(fn (string $state): string => $state === 'lista' ? 'success' : 'danger'),
                TextColumn::make('ultimo_error')->label('Último error')->limit(55)->placeholder('—')->tooltip(fn (MicrositioMapaCapa $record): ?string => $record->ultimo_error),
            ])
            ->headerActions([
                Action::make('sincronizarTodas')
                    ->label('Sincronizar todas')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->requiresConfirmation()
                    ->action(function (SincronizarCapasMapaInicio $sincronizador): void {
                        $this->notificarSincronizacion($sincronizador->sincronizar());
                    }),
            ])
            ->recordActions([
                Action::make('sincronizar')
                    ->label('Sincronizar ahora')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->visible(fn (MicrositioMapaCapa $record): bool => in_array($record->codigo, ['PETROLEO', 'AGRO-PROD'], true))
                    ->requiresConfirmation()
                    ->action(function (MicrositioMapaCapa $record, SincronizarCapasMapaInicio $sincronizador): void {
                        $fuente = $record->codigo === 'PETROLEO' ? 'petroleo' : 'agricultura';
                        $this->notificarSincronizacion($sincronizador->sincronizar($fuente));
                    }),
            ])
            ->defaultPaginationPageOption(10)
            ->poll('30s');
    }

    /** @param array<string, array{estado: string, periodo?: string, error?: string}> $resultados */
    private function notificarSincronizacion(array $resultados): void
    {
        $fallidas = collect($resultados)->where('estado', 'fallida');
        if ($fallidas->isEmpty()) {
            Notification::make()->title('Capas actualizadas desde las fuentes oficiales.')->success()->send();
        } else {
            Notification::make()
                ->title('Una o más fuentes no pudieron actualizarse.')
                ->body($fallidas->pluck('error')->filter()->implode(' '))
                ->danger()
                ->send();
        }

        $this->resetTable();
        $this->mount();
        $this->vista++;
    }

    public function restablecer(): void
    {
        $this->mount();
    }

    public function getDefaultTestingSchemaName(): ?string
    {
        return 'editorForm';
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        $registro = MicrositioMapa::query()->first();
        $estado = $registro === null ? 'borrador' : app(PublicadorMicrositio::class)->estadoDe('mapa', $registro);
        $capa = (string) ($this->data['capa_principal'] ?? 'POB-2026');

        return [
            'estado' => $estado,
            'vistaPrevia' => route('micrositio.inicio', ['preview' => 1]).'#capa='.$capa,
        ];
    }

    /**
     * @return list<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('verInicio')
                ->label('Ver inicio')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->url(fn (): string => route('micrositio.inicio'))
                ->openUrlInNewTab(),
            Action::make('vistaPrevia')
                ->label('Vista previa')
                ->icon(Heroicon::OutlinedEye)
                ->url(fn (): string => route('micrositio.inicio', ['preview' => 1]))
                ->openUrlInNewTab(),
            Action::make('publicar')
                ->label('Publicar')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Publicar el mapa del inicio')
                ->modalDescription('El sitio público pasará a mostrar el borrador actual de esta sección.')
                ->action(function (): void {
                    $this->guardar();
                    app(PublicadorMicrositio::class)->publicar('mapa');
                    Notification::make()->title('Mapa publicado en el micrositio.')->success()->send();
                }),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function opcionesCapas(): array
    {
        $opciones = [];

        foreach (app(FuenteDatosMapaInicio::class)->capas() as $capa) {
            $opciones[$capa['codigo']] = $capa['codigo'].' — '.$capa['nombre'].' ('.$capa['periodo'].')';
        }

        return $opciones;
    }

    /**
     * @return array<string, string>
     */
    private function periodos(string $codigo): array
    {
        $capa = app(FuenteDatosMapaInicio::class)->capa($codigo);

        if ($capa === null) {
            return [];
        }

        return [(string) $capa['periodo'] => (string) $capa['periodo']];
    }
}
