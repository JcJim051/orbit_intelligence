<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetaResultadoConstruccion extends Model
{
    protected $table = 'meta_resultado_construcciones';

    protected $fillable = [
        'meta_resultado_id',
        'assigned_to',
        'status',
        'measurement_mode',
        'baseline_value',
        'current_value',
        'current_value_date',
        'current_value_source',
        'methodology_notes',
        'management_progress_pct',
        'result_progress_pct',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'baseline_value' => 'decimal:4',
            'current_value' => 'decimal:4',
            'current_value_date' => 'date',
            'management_progress_pct' => 'decimal:4',
            'result_progress_pct' => 'decimal:4',
            'reviewed_at' => 'datetime',
        ];
    }

    public function metaResultado(): BelongsTo
    {
        return $this->belongsTo(MetaResultado::class, 'meta_resultado_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function comments()
    {
        return $this->hasMany(MetaResultadoConstruccionComentario::class, 'construccion_id')->latest();
    }
}
