<?php

namespace App\Models;

use Database\Factories\TabularDataSourceFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TabularDataSource extends Model
{
    /** @use HasFactory<TabularDataSourceFactory> */
    use HasFactory, HasUlids;

    protected $guarded = [];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(TabularDataSourceVersion::class);
    }

    public function currentVersion(): HasOne
    {
        return $this->hasOne(TabularDataSourceVersion::class)->ofMany('version', 'max');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
