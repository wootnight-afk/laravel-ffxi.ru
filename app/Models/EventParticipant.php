<?php

namespace App\Models;

use App\Enums\EventParticipantStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $joined_at
 * @property Carbon|null $left_at
 */
#[Fillable(['event_id', 'user_id', 'jobs', 'note', 'status', 'joined_at', 'left_at'])]
class EventParticipant extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
            'status' => EventParticipantStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
