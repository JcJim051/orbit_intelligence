<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiidGoogleSetting extends Model
{
    protected $table = 'siid_publicacion.google_settings';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'main_tabs' => 'array',
            'complementary_tabs' => 'array',
            'last_tested_at' => 'datetime',
        ];
    }
}
