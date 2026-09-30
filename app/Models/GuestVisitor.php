<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'uuid', 'display_name', 'ip_hash', 'user_agent',
    'first_seen_at', 'last_seen_at', 'hits',
    'converted_user_id', 'converted_at',
])]
class GuestVisitor extends Model
{
    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'converted_at' => 'datetime',
            'hits' => 'integer',
        ];
    }

    public function convertedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'converted_user_id');
    }

    public function isConverted(): bool
    {
        return $this->converted_user_id !== null;
    }
}
