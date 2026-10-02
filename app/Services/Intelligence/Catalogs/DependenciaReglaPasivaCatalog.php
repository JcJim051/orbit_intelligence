<?php

namespace App\Services\Intelligence\Catalogs;

use App\Enums\TipoReglaPasiva;
use App\Models\DependenciaReglaPasiva;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use App\Models\SectorMga;
use Illuminate\Database\Eloquent\Model;

class DependenciaReglaPasivaCatalog extends CatalogDefinition
{
    public function modelClass(): string
    {
        return DependenciaReglaPasiva::class;
    }

    public function title(): string
    {
        return 'Reglas de pasiva';
    }

    public function summary(): string
    {
        return 'Identifican la dependencia de una fila de la pasiva de Hacienda (InfMesPptoCDP). La relación directa BPIN → dependencia es la regla principal y predomina sobre cualquier unidad PCT, prefijo de rubro o sector MGA. Las demás reglas quedan como respaldo cuando no exista BPIN confirmado.';
    }

    public function routeName(): string
    {
        return 'intelligence.reglas-pasiva';
    }

    public function routeParameter(): string
    {
        return 'regla';
    }

    public function filename(): string
    {
        return 'reglas-pasiva';
    }

    public function filters(): array
    {
        return ['activo', 'tipo_regla'];
    }

    /** @var array<string, string>|null */
    private ?array $sectorNames = null;

    public function display(CatalogField $field, Model $record): string
    {
        $text = parent::display($field, $record);
        $tipo = $record->getAttribute('tipo_regla');
        $tipo = $tipo instanceof TipoReglaPasiva ? $tipo : TipoReglaPasiva::tryFrom((string) $tipo);

        if ($field->column !== 'valor' || $text === '—' || $tipo !== TipoReglaPasiva::SectorMga) {
            return $text;
        }

        $this->sectorNames ??= SectorMga::query()->pluck('nombre', 'codigo')->map(fn ($nombre): string => (string) $nombre)->all();
        $nombre = $this->sectorNames[str_pad($text, 2, '0', STR_PAD_LEFT)] ?? null;

        return $text.' — '.($nombre ?? 'sector MGA no registrado');
    }

    public function with(): array
    {
        return ['dependencia'];
    }

    public function searchColumns(): array
    {
        return ['valor', 'observacion', 'dependencia.codigo', 'dependencia.nombre'];
    }

    public function naturalKey(): array
    {
        return ['dependencia_id', 'tipo_regla', 'valor'];
    }

    public function excelNaturalKey(): array
    {
        return ['dependencia_codigo', 'tipo_regla', 'valor'];
    }

    public function order(Builder $query): void
    {
        $query->orderByDesc('prioridad')->orderBy('valor');
    }

    public function fields(): array
    {
        return [
            new CatalogField(
                column: 'dependencia_id',
                label: 'Dependencia',
                input: 'dependencia',
                required: true,
                naturalKey: true,
                excelHeader: 'dependencia_codigo',
                example: 'SEC. EJEMPLO',
                hint: 'En Excel use el código de la dependencia, no el nombre.',
            ),
            new CatalogField(
                column: 'tipo_regla',
                label: 'Tipo de regla',
                input: 'select',
                required: true,
                naturalKey: true,
                options: TipoReglaPasiva::options(),
                example: 'unidad_pct',
                hint: 'BPIN es autoridad: si coincide, gana aunque otra regla tenga mayor prioridad. Sin BPIN, se usa prioridad: prefijo de rubro 300, sector MGA 200, unidad PCT 100.',
            ),
            new CatalogField(
                column: 'valor',
                label: 'Valor',
                input: 'text',
                required: true,
                naturalKey: true,
                maxLength: 160,
                example: '9999',
                hint: 'Unidad PCT (0301), prefijo de rubro (0301 - 2.3.19), sector MGA (19) o BPIN de 13 a 15 dígitos. No invente la unidad de una secretaría.',
            ),
            new CatalogField(
                column: 'prioridad',
                label: 'Prioridad',
                input: 'number',
                minNumber: 1,
                maxNumber: 9999,
                example: 100,
                hint: 'Si la deja vacía, se usa la prioridad del tipo de regla.',
            ),
            new CatalogField(
                column: 'vigencia_desde',
                label: 'Vigencia desde',
                input: 'number',
                minNumber: 1990,
                maxNumber: 2100,
                example: null,
                hint: 'Año fiscal opcional. Vacío significa que la regla no tiene límite de vigencia.',
            ),
            new CatalogField(
                column: 'vigencia_hasta',
                label: 'Vigencia hasta',
                input: 'number',
                minNumber: 1990,
                maxNumber: 2100,
                example: null,
            ),
            new CatalogField(
                column: 'observacion',
                label: 'Observación',
                input: 'textarea',
                maxLength: 2000,
                example: 'Ejemplo. Reemplace esta fila. No asigne unidades reales hasta confirmarlas.',
            ),
            new CatalogField(
                column: 'activo',
                label: 'Activo',
                input: 'boolean',
                required: true,
                example: 1,
            ),
        ];
    }

    public function attributesFrom(array $normalized, bool $excel): array
    {
        $attributes = parent::attributesFrom($normalized, $excel);

        if (($attributes['prioridad'] ?? null) === null && isset($attributes['tipo_regla'])) {
            $tipo = TipoReglaPasiva::tryFrom((string) $attributes['tipo_regla']);

            if ($tipo instanceof TipoReglaPasiva) {
                $attributes['prioridad'] = $tipo->prioridadPorDefecto();
            }
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<mixed>
     */
    protected function fieldRules(CatalogField $field, array $payload, bool $excel, ?int $ignoreId): array
    {
        $rules = parent::fieldRules($field, $payload, $excel, $ignoreId);

        if ($field->column === 'valor') {
            $tipo = (string) ($payload['tipo_regla'] ?? '');
            $rules[] = $this->valorRule($tipo);
        }

        if ($field->column === 'valor' && ! $excel) {
            $rules[] = Rule::unique($this->table(), 'valor')
                ->where('dependencia_id', $payload['dependencia_id'] ?? null)
                ->where('tipo_regla', $payload['tipo_regla'] ?? null)
                ->ignore($ignoreId);
        }

        return $rules;
    }

    protected function afterValidation(Validator $validator, array $payload): void
    {
        $desde = $payload['vigencia_desde'] ?? null;
        $hasta = $payload['vigencia_hasta'] ?? null;

        if ($desde !== null && $hasta !== null && (int) $hasta < (int) $desde) {
            $validator->errors()->add('vigencia_hasta', 'La vigencia hasta no puede ser anterior a la vigencia desde.');
        }
    }

    private function valorRule(string $tipo): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($tipo): void {
            $text = trim((string) $value);

            $valid = match ($tipo) {
                TipoReglaPasiva::Bpin->value => preg_match('/^\d{13,15}$/', $text) === 1,
                TipoReglaPasiva::UnidadPct->value => preg_match('/^\d{1,12}$/', $text) === 1,
                TipoReglaPasiva::SectorMga->value => preg_match('/^\d{1,3}$/', $text) === 1,
                TipoReglaPasiva::PrefijoRubro->value => preg_match('/^.+\s-\s.+$/', $text) === 1,
                default => false,
            };

            if ($valid) {
                return;
            }

            $fail(match ($tipo) {
                TipoReglaPasiva::Bpin->value => 'El BPIN debe tener entre 13 y 15 dígitos.',
                TipoReglaPasiva::UnidadPct->value => 'La unidad PCT debe ser numérica, por ejemplo 0301.',
                TipoReglaPasiva::SectorMga->value => 'El sector MGA debe ser el segmento numérico, por ejemplo 19.',
                TipoReglaPasiva::PrefijoRubro->value => 'El prefijo de rubro debe incluir la unidad y el rubro separados por « - », por ejemplo 0301 - 2.3.19.',
                default => 'El valor no corresponde al tipo de regla.',
            });
        };
    }
}
