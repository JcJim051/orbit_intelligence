<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class IndicadorTerritorial extends Model
{
    use SoftDeletes;

    public const ESTADOS = [
        'precargado' => 'Precargado',
        'revision_tecnica' => 'En revisión técnica',
        'pendiente_historico' => 'Pendiente de histórico',
        'historico_cargado' => 'Histórico cargado',
        'historico_observado' => 'Histórico con observaciones',
        'historico_validado' => 'Histórico validado',
        'listo_habilitar' => 'Listo para habilitar',
        'activo' => 'Seguimiento periódico activo',
    ];

    public const PERIODICIDADES = [
        'diaria' => 'Diaria (consolidación mensual)',
        'semanal' => 'Semanal (consolidación mensual)',
        'mensual' => 'Mensual',
        'trimestral' => 'Trimestral',
        'cuatrimestral' => 'Cuatrimestral',
        'semestral' => 'Semestral',
        'anual' => 'Anual',
        'combinada' => 'Combinada / por definir',
    ];

    public const UNIDADES_MEDIDA = [
        'Número' => 'Número',
        'Porcentaje' => 'Porcentaje (%)',
        'Tasa' => 'Tasa',
        'Razón' => 'Razón',
        'Índice' => 'Índice',
        'Promedio' => 'Promedio',
        'Pesos' => 'Pesos ($)',
        'Personas' => 'Personas',
        'Hogares' => 'Hogares',
        'Viviendas' => 'Viviendas',
        'Entidades' => 'Entidades',
        'Eventos' => 'Eventos',
        'Casos' => 'Casos',
        'Hectáreas' => 'Hectáreas',
        'Kilómetros' => 'Kilómetros',
        'Metros cuadrados' => 'Metros cuadrados',
        'Días' => 'Días',
        'Horas' => 'Horas',
    ];

    protected $table = 'indicadores_territoriales';

    protected $fillable = [
        'codigo', 'nombre', 'convencion', 'definicion', 'objetivo', 'dependencia_id', 'dependencia_fuente',
        'area_tematica', 'tipo_indicador', 'unidad_medida', 'formula_calculo', 'variables_calculo',
        'periodicidad_original', 'periodicidad', 'fuente_informacion', 'responsable_informacion',
        'desagregaciones', 'cobertura_geografica', 'linea_base_valor', 'linea_base_periodo',
        'observaciones_tecnicas', 'limitaciones', 'grupo_asignacion', 'estado', 'asignado_a', 'aprobado_por',
        'aprobado_at', 'fuente_importacion', 'fila_fuente',
    ];

    protected function casts(): array
    {
        return [
            'linea_base_valor' => 'decimal:6',
            'aprobado_at' => 'datetime',
        ];
    }

    public function dependencia(): BelongsTo
    {
        return $this->belongsTo(Dependencia::class);
    }

    public function asignado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignado_a');
    }

    public function aprobador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(IndicadorTerritorialAsignacion::class)->latest('asignado_at');
    }

    public function datos(): HasMany
    {
        return $this->hasMany(IndicadorTerritorialDato::class)->orderBy('periodo_inicio')->orderBy('periodo');
    }

    public function actualizarLineaBaseHistorica(): void
    {
        $primero = $this->datos()
            ->where('estado', 'aprobado')
            ->orderByRaw('case when periodo_inicio is null then 1 else 0 end')
            ->orderBy('periodo_inicio')
            ->orderBy('periodo')
            ->first();

        $this->forceFill([
            'linea_base_valor' => $primero?->valor,
            'linea_base_periodo' => $primero?->periodo,
        ])->save();
    }

    public function scopeVisiblePara(Builder $query, User $user): Builder
    {
        if ($user->canCoordinateTerritorialIndicators()) {
            return $query;
        }

        return $query->where('asignado_a', $user->id);
    }
}
