@php($editingField = $field ?? null)
@php($inheritedField = $editingField && isset($draft) && $editingField->introduced_in_version < $draft->version)
@php($catalogOptions = is_array($editingField?->options) ? $editingField->options : [])
<label class="field"><span>Etiqueta</span><input name="label" value="{{ $editingField?->label }}" required maxlength="120" placeholder="Nivel de riesgo"></label>
<label class="field"><span>Código estable</span><input name="key" value="{{ $editingField?->key }}" required maxlength="63" pattern="[a-z][a-z0-9_]*" placeholder="nivel_riesgo" @readonly($inheritedField)><small>{{ $inheritedField ? 'Bloqueado para conservar los datos históricos.' : 'No debe cambiar después de publicar.' }}</small></label>
<label class="field"><span>Sección</span><input name="section" value="{{ $editingField?->section ?? 'Información general' }}" required maxlength="120"></label>
<label class="field"><span>Tipo de campo</span>@if($inheritedField)<input type="hidden" name="field_type" value="{{ $editingField->field_type->value }}">@endif<select name="field_type" required @disabled($inheritedField)>@foreach($fieldTypes as $type)<option value="{{ $type->value }}" @selected($editingField?->field_type === $type)>{{ $type->label() }}</option>@endforeach</select></label>
<label class="field"><span>Unidad</span><input name="unit" value="{{ $editingField?->unit }}" maxlength="50" placeholder="personas, ha, horas"></label>
<label class="field"><span>Orden</span><input type="number" name="sort_order" value="{{ $editingField?->sort_order ?? 10 }}" min="0" max="1000" required></label>
<label class="field sm:col-span-2 lg:col-span-3"><span>Ayuda para el técnico</span><textarea name="help_text" rows="2" maxlength="500">{{ $editingField?->help_text }}</textarea></label>
<label class="field sm:col-span-2 lg:col-span-3"><span>Opciones del catálogo</span><textarea name="options" rows="2" maxlength="5000" placeholder="Bajo, Medio, Alto, Crítico">{{ implode(', ', $catalogOptions) }}</textarea><small>Obligatorias para listas; sepárelas con comas o saltos de línea.</small></label>
<label class="field"><span>Valor mínimo</span><input type="number" step="any" name="min_value" value="{{ data_get($editingField?->validation_rules, 'min') }}"></label>
<label class="field"><span>Valor máximo</span><input type="number" step="any" name="max_value" value="{{ data_get($editingField?->validation_rules, 'max') }}"></label>
<label class="field"><span>Longitud máxima</span><input type="number" name="max_length" value="{{ data_get($editingField?->validation_rules, 'max_length') }}" min="1" max="10000"></label>
<label class="field sm:col-span-2 lg:col-span-3"><span>Tratamiento histórico</span><select name="historical_policy" required>@foreach($historicalPolicies as $policy)<option value="{{ $policy->value }}" @selected(($editingField?->historical_policy ?? \App\Enums\HistoricalDataPolicy::FutureOnly) === $policy)>{{ $policy->label() }}</option>@endforeach</select><small>Define qué ocurrirá con registros creados antes de que exista este campo.</small></label>
<div class="sm:col-span-2 lg:col-span-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
    <input type="hidden" name="required" value="0"><label class="check"><input type="checkbox" name="required" value="1" @checked($editingField?->required)> Obligatorio</label>
    <input type="hidden" name="visible_in_qgis" value="0"><label class="check"><input type="checkbox" name="visible_in_qgis" value="1" @checked($editingField?->visible_in_qgis ?? true)> Visible en QGIS</label>
    <input type="hidden" name="public_visible" value="0"><label class="check"><input type="checkbox" name="public_visible" value="1" @checked($editingField?->public_visible)> Visible públicamente</label>
    <input type="hidden" name="available_for_analytics" value="0"><label class="check"><input type="checkbox" name="available_for_analytics" value="1" @checked($editingField?->available_for_analytics ?? true)> Disponible para analítica</label>
</div>
<button class="btn-primary sm:col-span-2 lg:col-span-3">{{ $submitLabel }}</button>
