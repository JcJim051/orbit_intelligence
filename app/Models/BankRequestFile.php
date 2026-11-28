<?php

namespace App\Models;

use App\Enums\BankRequestFileKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankRequestFile extends Model
{
    protected $fillable = [
        'bank_request_id',
        'version',
        'kind',
        'file_path',
        'original_filename',
        'file_mime_type',
        'file_size',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'kind' => BankRequestFileKind::class,
            'file_size' => 'integer',
        ];
    }

    public function bankRequest(): BelongsTo
    {
        return $this->belongsTo(BankRequest::class);
    }

    public function esPdf(): bool
    {
        if (! str_ends_with(strtolower($this->original_filename), '.pdf')) {
            return false;
        }

        return in_array($this->file_mime_type, [
            'application/pdf',
            'application/x-pdf',
            'application/octet-stream',
        ], true);
    }
}
