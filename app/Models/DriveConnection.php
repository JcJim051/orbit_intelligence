<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class DriveConnection extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected $hidden = ['access_token', 'refresh_token'];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'last_verified_at' => 'datetime',
            'scopes' => 'array',
            'active' => 'boolean',
        ];
    }

    public function exports()
    {
        return $this->hasMany(DriveExport::class);
    }
}
