<?php

namespace App\Models;

use App\Enums\DatasetFieldType;
use App\Enums\HistoricalDataPolicy;
use Database\Factories\DatasetFormFieldFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatasetFormField extends Model
{
    /** @use HasFactory<DatasetFormFieldFactory> */
    use HasFactory, HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'field_type' => DatasetFieldType::class,
            'required' => 'boolean',
            'validation_rules' => 'array',
            'historical_policy' => HistoricalDataPolicy::class,
            'introduced_in_version' => 'integer',
            'visible_in_qgis' => 'boolean',
            'public_visible' => 'boolean',
            'available_for_analytics' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Normaliza catálogos heredados que pudieron migrarse como una cadena JSON.
     *
     * @return Attribute<array<int, string>|null, array<int, string>|string|null>
     */
    protected function options(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value): ?array {
                if ($value === null || $value === '') {
                    return null;
                }

                $decoded = is_string($value) ? json_decode($value, true) : $value;
                if (is_string($decoded)) {
                    $nested = json_decode($decoded, true);
                    $decoded = is_array($nested) ? $nested : preg_split('/[\r\n,]+/', $decoded);
                }

                if (! is_array($decoded)) {
                    return null;
                }

                return collect($decoded)
                    ->filter(fn (mixed $option): bool => is_scalar($option))
                    ->map(fn (mixed $option): string => trim((string) $option))
                    ->filter()
                    ->values()
                    ->all();
            },
            set: fn (array|string|null $value): ?string => $value === null
                ? null
                : json_encode(is_array($value) ? array_values($value) : [$value], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
    }

    public function formVersion(): BelongsTo
    {
        return $this->belongsTo(DatasetFormVersion::class, 'dataset_form_version_id');
    }
}
