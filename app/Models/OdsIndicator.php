<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OdsIndicator extends Model
{
    protected $fillable = ['ods_target_id', 'code', 'name', 'unit', 'description', 'source', 'csv_url', 'excel_url', 'imported_at', 'active'];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'imported_at' => 'datetime',
        ];
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(OdsTarget::class, 'ods_target_id');
    }

    public function links(): HasMany
    {
        return $this->hasMany(IndicadorResultadoOdsLink::class);
    }
}
