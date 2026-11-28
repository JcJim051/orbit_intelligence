<?php

namespace App\Models;

use App\Enums\BankRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankRequestEvent extends Model
{
    protected $fillable = [
        'bank_request_id',
        'user_id',
        'from_status',
        'to_status',
        'observation',
    ];

    protected function casts(): array
    {
        return [
            'from_status' => BankRequestStatus::class,
            'to_status' => BankRequestStatus::class,
        ];
    }

    public function bankRequest(): BelongsTo
    {
        return $this->belongsTo(BankRequest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
