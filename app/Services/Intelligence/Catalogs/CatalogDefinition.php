<?php

namespace App\Services\Intelligence\Catalogs;

use App\Models\Dependencia;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class CatalogDefinition
{
    abstract public function modelClass(): string;

    abstract public function title(): string;

    abstract public function summary(): string;

    abstract public function routeName(): string;

    abstract public function routeParameter(): string;

    abstract public function filename(): string;

    /**
     * @return list<CatalogField>
     */
    abstract public function fields(): array;

    /**
     * @return list<string>
     */
    abstract public function searchColumns(): array;

    /**
     * @return list<string>
     */
    abstract public function naturalKey(): array;

    /**
     * @return list<string>
     */
    public function filters(): array
    {
        return ['activo'];
    }

    /**
     * @return list<string>
     */
    public function with(): array
    {
        return [];
    }

    public function order(Builder $query): void
    {
        $query->orderBy($this->naturalKey()[0]);
    }

    public function usesSoftDeletes(): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($this->modelClass()), true);
    }

    /**
     * Conteos de relaciones que se muestran en el listado.
     *
     * @return array<string, string>
     */
    public function counts(): array
    {
        return [];
    }

    /**
     * Relaciones que impiden el borrado mientras tengan filas vigentes.
     *
     * @return array<string, string>
     */
    public function childRelations(): array
    {
        return [];
    }

    public function deletionBlockReason(Model $record): ?string
    {
        foreach ($this->childRelations() as $relation => $label) {
            if ($record->{$relation}()->exists()) {
                return 'No se puede eliminar porque tiene '.$label.' asociados.';
            }
        }

        return null;
    }

    public function newQuery(): Builder
    {
        $model = $this->modelClass();
        $query = $model::query()->with($this->with());

        if ($this->counts() !== []) {
            $query->withCount(array_keys($this->counts()));
        }

        return $query;
    }

    public function exportFilename(): string
    {
        return $this->filename().'.xlsx';
    }

    public function templateFilename(): string
    {
        return 'plantilla-'.$this->filename().'.xlsx';
    }

    public function destroyLabel(): string
    {
        return $this->usesSoftDeletes() ? 'Eliminar' : 'Desactivar';
    }

    public function destroyConfirm(): string
    {
        return $this->usesSoftDeletes()
            ? '¿Eliminar este registro? Dejará de verse en el listado.'
            : '¿Desactivar este registro? Seguirá en el listado como inactivo.';
    }

    /**
     * @return list<CatalogField>
     */
    public function excelFields(): array
    {
        return array_values(array_filter(
            $this->fields(),
            fn (CatalogField $field): bool => $field->onExcel,
        ));
    }

    /**
     * @return list<string>
     */
    public function excelHeaders(): array
    {
        return array_map(
            fn (CatalogField $field): string => $field->excelHeader(),
            $this->excelFields(),
        );
    }

    /**
     * @return list<string>
     */
    public function excelNaturalKey(): array
    {
        return array_map(
            fn (CatalogField $field): string => $field->excelHeader(),
            array_values(array_filter(
                $this->excelFields(),
                fn (CatalogField $field): bool => $field->naturalKey,
            )),
        );
    }

    /**
     * @return list<mixed>
     */
    public function exampleRow(): array
    {
        return array_map(
            fn (CatalogField $field): mixed => $field->example,
            $this->excelFields(),
        );
    }

    /**
     * @return list<string>
     */
    public function instructionLines(): array
    {
        $lines = [
            'Esta plantilla carga el catálogo «'.$this->title().'».',
            'La hoja Datos trae los encabezados exactos y una fila de ejemplo. Elimine o reemplace el ejemplo antes de importar.',
            'Los datos se leen de la primera hoja. Esta hoja de instrucciones no se importa.',
            'La importación crea o actualiza por la llave: '.implode(', ', $this->excelNaturalKey()).'.',
            'Si alguna fila tiene errores, no se guarda ningún cambio.',
            'activo: 1 = activo, 0 = inactivo. También se aceptan sí y no.',
        ];

        foreach ($this->excelFields() as $field) {
            if ($field->options !== null) {
                $allowed = [];

                foreach ($field->options as $value => $label) {
                    $allowed[] = $value.' ('.$label.')';
                }

                $lines[] = 'Valores permitidos para '.$field->excelHeader().': '.implode(', ', $allowed).'.';
            }

            if ($field->hint !== null) {
                $lines[] = $field->label.': '.$field->hint;
            }
        }

        return $lines;
    }

    public function applySearch(Builder $query, string $term): void
    {
        $like = $this->likePattern($term);

        $query->where(function (Builder $query) use ($like): void {
            foreach ($this->searchColumns() as $column) {
                if (str_contains($column, '.')) {
                    [$relation, $relatedColumn] = explode('.', $column, 2);
                    $query->orWhereHas($relation, function (Builder $query) use ($relatedColumn, $like): void {
                        $query->whereRaw($relatedColumn.' like ? escape ?', [$like, '\\']);
                    });

                    continue;
                }

                $query->orWhereRaw($column.' like ? escape ?', [$like, '\\']);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function normalize(array $input, bool $excel): array
    {
        $normalized = [];

        foreach ($this->fields() as $field) {
            if ($excel && ! $field->onExcel) {
                continue;
            }

            if (! $excel && ! $field->onForm) {
                continue;
            }

            $key = $excel ? $field->excelHeader() : $field->formKey();
            $normalized[$key] = $this->normalizeValue($field, $input[$key] ?? null, $excel);
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, list<mixed>>
     */
    public function rules(array $payload, bool $excel, ?int $ignoreId = null): array
    {
        $rules = [];

        foreach ($this->fields() as $field) {
            if ($excel && ! $field->onExcel) {
                continue;
            }

            if (! $excel && ! $field->onForm) {
                continue;
            }

            $key = $excel ? $field->excelHeader() : $field->formKey();
            $rules[$key] = $this->fieldRules($field, $payload, $excel, $ignoreId);
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function makeValidator(array $payload, bool $excel, ?int $ignoreId = null): Validator
    {
        $validator = validator($payload, $this->rules($payload, $excel, $ignoreId), $this->messages());

        $validator->after(function (Validator $validator) use ($payload): void {
            $this->afterValidation($validator, $payload);
        });

        return $validator;
    }

    /**
     * @param  array<string, mixed>  $normalized
     * @return array<string, mixed>
     */
    public function attributesFrom(array $normalized, bool $excel): array
    {
        $attributes = [];

        foreach ($this->fields() as $field) {
            $key = $excel ? $field->excelHeader() : $field->formKey();

            if (! array_key_exists($key, $normalized)) {
                continue;
            }

            $value = $normalized[$key];

            if ($field->input === 'dependencia') {
                if ($value === null || $value === '') {
                    $attributes['dependencia_id'] = null;

                    continue;
                }

                $attributes['dependencia_id'] = $excel
                    ? Dependencia::query()->where('codigo', $value)->value('id')
                    : $value;

                continue;
            }

            if ($field->input === 'boolean') {
                $attributes[$field->column] = in_array($value, ['1', 1, true], true);

                continue;
            }

            if ($field->input === 'number') {
                $attributes[$field->column] = $value === null || $value === '' ? null : (int) $value;

                continue;
            }

            if ($field->input === 'decimal') {
                $attributes[$field->column] = $value === null || $value === '' ? null : $value;

                continue;
            }

            if ($field->input === 'lookup') {
                $attributes[$field->column] = $this->lookupId($field, $value, $excel);

                continue;
            }

            $attributes[$field->column] = $value;
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    public function excelFingerprint(array $normalized): string
    {
        $parts = [];

        foreach ($this->excelNaturalKey() as $column) {
            $parts[] = mb_strtolower(trim((string) ($normalized[$column] ?? '')));
        }

        return implode('|', $parts);
    }

    public function fieldByColumn(string $column): ?CatalogField
    {
        foreach ($this->fields() as $field) {
            if ($field->column === $column) {
                return $field;
            }
        }

        return null;
    }

    public function fieldByFormKey(string $key): ?CatalogField
    {
        foreach ($this->fields() as $field) {
            if ($field->formKey() === $key) {
                return $field;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<mixed>
     */
    protected function fieldRules(CatalogField $field, array $payload, bool $excel, ?int $ignoreId): array
    {
        $presence = $field->required ? 'required' : 'nullable';

        if ($field->input === 'boolean') {
            return ['required', Rule::in(['1', '0'])];
        }

        if ($field->input === 'select') {
            return [$presence, Rule::in(array_keys($field->options ?? []))];
        }

        if ($field->input === 'number') {
            return array_values(array_filter([
                $presence,
                'integer',
                $field->minNumber !== null ? 'min:'.$field->minNumber : null,
                $field->maxNumber !== null ? 'max:'.$field->maxNumber : null,
            ]));
        }

        if ($field->input === 'decimal') {
            return [$presence, 'numeric'];
        }

        if ($field->input === 'dependencia') {
            $dependency = $field->required ? 'required' : 'nullable';

            if ($excel) {
                return [$dependency, 'string', 'max:50', Rule::exists('dependencias', 'codigo')->whereNull('deleted_at')];
            }

            return [$dependency, 'integer', Rule::exists('dependencias', 'id')->whereNull('deleted_at')];
        }

        if ($field->input === 'lookup') {
            return $this->lookupRules($field, $excel);
        }

        $rules = [$presence, 'string', ...$field->extraRules];

        if ($field->maxLength !== null) {
            $rules[] = 'max:'.$field->maxLength;
        }

        if (! $excel && ($field->unique || ($field->naturalKey && $this->naturalKey() === [$field->column]))) {
            $rules[] = Rule::unique($this->table(), $field->column)->ignore($ignoreId);
        }

        return $rules;
    }

    protected function afterValidation(Validator $validator, array $payload): void {}

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'codigo.required' => 'El código es obligatorio.',
            'codigo.unique' => 'Ya existe un registro con ese código.',
            'codigo.max' => 'El código no puede superar :max caracteres.',
            'nombre.required' => 'El nombre es obligatorio.',
            'tipo.required' => 'El tipo es obligatorio.',
            'tipo.in' => 'El tipo no está permitido.',
            'codigo_dane.required' => 'El código DANE es obligatorio.',
            'codigo_dane.unique' => 'Ya existe un municipio con ese código DANE.',
            'codigo_dane.regex' => 'El código DANE debe tener 5 dígitos.',
            'dependencia_codigo.required' => 'El código de la dependencia es obligatorio.',
            'dependencia_codigo.exists' => 'No existe una dependencia con ese código.',
            'dependencia_id.required' => 'Seleccione una dependencia.',
            'dependencia_id.exists' => 'La dependencia seleccionada no existe.',
            'tipo_regla.required' => 'El tipo de regla es obligatorio.',
            'tipo_regla.in' => 'El tipo de regla no está permitido.',
            'valor.required' => 'El valor es obligatorio.',
            'valor.unique' => 'Ya existe una regla con esa dependencia, tipo y valor.',
            'prioridad.integer' => 'La prioridad debe ser un número entero.',
            'prioridad.min' => 'La prioridad debe ser al menos 1.',
            'prioridad.max' => 'La prioridad no puede superar 9999.',
            'vigencia_desde.integer' => 'La vigencia desde debe ser un año.',
            'vigencia_hasta.integer' => 'La vigencia hasta debe ser un año.',
            'activo.required' => 'Indique si el registro está activo.',
            'activo.in' => 'Activo debe ser 1 o 0.',
            'codigo.regex' => 'El código debe tener 11 dígitos.',
            'codigo_provisional.required' => 'El código provisional es obligatorio.',
            'codigo_provisional.unique' => 'Ya existe una meta de resultado con ese código provisional.',
            'descripcion.required' => 'La descripción es obligatoria.',
            'nombre.unique' => 'Ya existe un registro con ese nombre.',
            'pilar_id.required' => 'Seleccione un pilar.',
            'pilar_id.exists' => 'El pilar seleccionado no existe.',
            'pilar_codigo.required' => 'El código del pilar es obligatorio.',
            'pilar_codigo.exists' => 'No existe un pilar con ese código.',
            'eje_id.required' => 'Seleccione un eje.',
            'eje_id.exists' => 'El eje seleccionado no existe.',
            'eje_codigo.required' => 'El código del eje es obligatorio.',
            'eje_codigo.exists' => 'No existe un eje con ese código.',
            'linea_id.required' => 'Seleccione una línea.',
            'linea_id.exists' => 'La línea seleccionada no existe.',
            'linea_codigo.required' => 'El código de la línea es obligatorio.',
            'linea_codigo.exists' => 'No existe una línea con ese código.',
            'programa_id.exists' => 'El programa seleccionado no existe.',
            'programa_codigo.exists' => 'No existe un programa con ese código.',
            'subprograma_id.required' => 'Seleccione un subprograma.',
            'subprograma_id.exists' => 'El subprograma seleccionado no existe.',
            'subprograma_codigo.required' => 'El código del subprograma es obligatorio.',
            'subprograma_codigo.exists' => 'No existe un subprograma con ese código.',
            'sector_mga_id.required' => 'Seleccione un sector MGA.',
            'sector_mga_id.exists' => 'El sector MGA seleccionado no existe.',
            'sector_codigo.required' => 'El código del sector MGA es obligatorio.',
            'sector_codigo.exists' => 'No existe un sector MGA con ese código.',
            'sector_codigo.regex' => 'El código del sector debe tener 2 dígitos.',
            'indicador_resultado_id.required' => 'Seleccione un indicador de resultado.',
            'indicador_resultado_id.exists' => 'El indicador seleccionado no existe.',
            'indicador_nombre.required' => 'El nombre del indicador es obligatorio.',
            'indicador_nombre.exists' => 'No existe un indicador de resultado con ese nombre.',
            'meta_resultado_id.exists' => 'La meta de resultado seleccionada no existe.',
            'meta_resultado_codigo.exists' => 'No existe una meta de resultado con ese código provisional.',
            'orientacion.in' => 'La orientación no está permitida.',
            'linea_base.numeric' => 'La línea base debe ser numérica.',
            'meta_cuatrienio.numeric' => 'La meta de cuatrienio debe ser numérica.',
        ];
    }

    protected function table(): string
    {
        $model = $this->modelClass();

        return (new $model)->getTable();
    }

    protected function normalizeValue(CatalogField $field, mixed $value, bool $excel): mixed
    {
        if ($value instanceof DateTimeInterface) {
            $value = $value->format('Y-m-d');
        }

        if (is_float($value)) {
            $value = floor($value) == $value ? sprintf('%.0f', $value) : (string) $value;
        }

        if (is_int($value)) {
            $value = (string) $value;
        }

        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        }

        if (is_string($value)) {
            $value = trim($value);

            if ($value === '') {
                $value = null;
            }
        }

        if ($value === null) {
            return null;
        }

        if ($field->input === 'boolean') {
            return $this->normalizeBoolean($value);
        }

        if ($field->input === 'select') {
            return $this->normalizeOption($field, (string) $value);
        }

        if ($field->input === 'number' && is_numeric($value)) {
            return (int) $value;
        }

        if ($field->input === 'dependencia' && ! $excel && is_numeric($value)) {
            return (int) $value;
        }

        if ($field->input === 'lookup' && ! $excel && is_numeric($value)) {
            return (int) $value;
        }

        if ($field->input === 'decimal') {
            $text = str_replace(',', '.', (string) $value);

            return is_numeric($text) ? $text : $value;
        }

        return $value;
    }

    protected function normalizeBoolean(mixed $value): mixed
    {
        $text = mb_strtolower(trim((string) $value));

        return match ($text) {
            '1', 'true', 'si', 'sí', 'yes', 'activo', 'activa' => '1',
            '0', 'false', 'no', 'inactivo', 'inactiva' => '0',
            default => $value,
        };
    }

    protected function normalizeOption(CatalogField $field, string $value): string
    {
        foreach ($field->options ?? [] as $option => $label) {
            if (mb_strtolower($value) === mb_strtolower((string) $option) || mb_strtolower($value) === mb_strtolower($label)) {
                return (string) $option;
            }
        }

        return $value;
    }

    protected function likePattern(string $term): string
    {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);

        return '%'.$escaped.'%';
    }

    public function display(CatalogField $field, Model $record): string
    {
        if ($field->input === 'dependencia') {
            $dependencia = $record->getRelationValue('dependencia');

            return $dependencia instanceof Dependencia
                ? $dependencia->codigo.' — '.$dependencia->nombre
                : '—';
        }

        if ($field->input === 'lookup') {
            $related = $record->getRelationValue((string) $field->lookupRelation);

            return $related instanceof Model ? $this->lookupOptionLabel($field, $related) : '—';
        }

        $value = $record->getAttribute($field->column);

        if ($field->input === 'boolean') {
            return $value ? 'Activo' : 'Inactivo';
        }

        if ($value instanceof BackedEnum) {
            return $field->options[$value->value] ?? (string) $value->value;
        }

        if ($value === null || $value === '') {
            return '—';
        }

        return (string) $value;
    }

    public function formValue(CatalogField $field, ?Model $record): mixed
    {
        if ($record === null) {
            return $field->input === 'boolean' ? true : null;
        }

        if ($field->input === 'dependencia' || $field->input === 'lookup') {
            return $record->getAttribute($field->formKey());
        }

        $value = $record->getAttribute($field->column);

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        return $value;
    }

    public function lookupOptionLabel(CatalogField $field, Model $option): string
    {
        if ($field->input === 'dependencia') {
            return $option->getAttribute('codigo').' — '.$option->getAttribute('nombre');
        }

        $code = $option->getAttribute($field->lookupColumn);
        $name = (string) $option->getAttribute($field->lookupDisplay);

        if ($field->lookupColumn === $field->lookupDisplay || $code === null || $code === '') {
            return $name;
        }

        $text = $code.' — '.$name;

        return mb_strlen($text) > 140 ? mb_substr($text, 0, 137).'…' : $text;
    }

    /**
     * @return list<mixed>
     */
    private function lookupRules(CatalogField $field, bool $excel): array
    {
        $presence = $field->required ? 'required' : 'nullable';
        $model = $field->lookupModel;
        $instance = new $model;
        $column = $excel ? $field->lookupColumn : 'id';
        $exists = Rule::exists($instance->getTable(), $column);

        if (in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
            $exists = $exists->whereNull('deleted_at');
        }

        if ($excel) {
            $rules = [$presence, 'string', $exists];

            if ($field->maxLength !== null) {
                $rules[] = 'max:'.$field->maxLength;
            }

            return $rules;
        }

        return [$presence, 'integer', $exists];
    }

    private function lookupId(CatalogField $field, mixed $value, bool $excel): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! $excel) {
            return (int) $value;
        }

        $model = $field->lookupModel;
        $id = $model::query()->where($field->lookupColumn, $value)->value('id');

        return $id === null ? null : (int) $id;
    }
}
