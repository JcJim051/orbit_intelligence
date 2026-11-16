<?php

namespace App\Filament\Pages;

use App\Contracts\Micrositio\FuenteDatosMapaInicio;
use App\Models\MicrositioMapa;
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
use UnitEnum;

class MapaDelInicio extends Page implements HasForms
{
    use InteractsWithForms;

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
        $this->form->fill($registro?->atributosPublicables() ?? [
            'mostrar_bloque' => true,
            'capa_principal' => 'NBI-2018',
            'capas_adicionales' => ['POB-2026', 'IPM-2018', 'EDU-CN-2024'],
            'periodo_modo' => 'ultimo',
            'paleta' => 'auto',
            'polaridad' => 'auto',
            'texto_tarjeta' => '29 municipios · capital Villavicencio',
            'texto_cta' => 'Explorar en el visor',
        ]);
    }

    public function form(Schema $schema): Schema
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
        $datos = $this->form->getState();
        $datos['capas_adicionales'] = array_values(array_filter(
            (array) ($datos['capas_adicionales'] ?? []),
            fn (mixed $codigo): bool => is_string($codigo) && $codigo !== '' && $codigo !== ($datos['capa_principal'] ?? null),
        ));

        $registro = MicrositioMapa::query()->first() ?? new MicrositioMapa;
        $registro->fill($datos)->save();
        $this->vista++;

        Notification::make()->title('Borrador del mapa guardado.')->success()->send();
    }

    public function restablecer(): void
    {
        $this->mount();
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        $registro = MicrositioMapa::query()->first();
        $estado = $registro === null ? 'borrador' : app(PublicadorMicrositio::class)->estadoDe('mapa', $registro);
        $capa = (string) ($this->data['capa_principal'] ?? 'NBI-2018');

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
