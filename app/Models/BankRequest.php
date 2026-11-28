<?php

namespace App\Models;

use App\Enums\BankRequestFileKind;
use App\Enums\BankRequestStatus;
use Database\Factories\BankRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankRequest extends Model
{
    /** @use HasFactory<BankRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'reference',
        'numero_seguimiento',
        'dependencia_id',
        'secretariat',
        'subject',
        'official_name',
        'email',
        'status',
        'file_path',
        'original_filename',
        'file_mime_type',
        'file_size',
    ];

    protected function casts(): array
    {
        return [
            'status' => BankRequestStatus::class,
            'file_size' => 'integer',
        ];
    }

    public function dependencia(): BelongsTo
    {
        return $this->belongsTo(Dependencia::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(BankRequestFile::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(BankRequestEvent::class);
    }

    public function archivoActual(BankRequestFileKind $kind): ?BankRequestFile
    {
        return $this->files()
            ->where('kind', $kind)
            ->orderByDesc('version')
            ->orderByDesc('id')
            ->first();
    }
}
