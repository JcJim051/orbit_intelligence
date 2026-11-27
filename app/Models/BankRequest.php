<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankRequest extends Model
{
    protected $fillable = [
        'reference', 'secretariat', 'procedure_type', 'subject', 'official_name', 'email',
        'file_path', 'original_filename', 'file_mime_type', 'file_size',
    ];

    protected function casts(): array
    {
        return ['file_size' => 'integer'];
    }
}
