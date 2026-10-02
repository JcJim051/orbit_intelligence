<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OdsTarget extends Model
{
    protected $fillable = ['ods_goal_id', 'code', 'name', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function goal(): BelongsTo
    {
        return $this->belongsTo(OdsGoal::class, 'ods_goal_id');
    }

    public function indicators(): HasMany
    {
        return $this->hasMany(OdsIndicator::class);
    }
}
