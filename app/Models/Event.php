<?php

namespace App\Models;

use App\Enums\EventStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $starts_at
 * @property Carbon|null $registration_close
 * @property EventStatus $status
 */
#[Fillable([
    'user_id', 'type_id', 'title', 'description', 'location', 'starts_at',
    'duration_minutes', 'max_participants', 'registration_close', 'status',
])]
class Event extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'registration_close' => 'datetime',
            'duration_minutes' => 'integer',
            'max_participants' => 'integer',
            'status' => EventStatus::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<EventType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(EventType::class, 'type_id');
    }

    /**
     * @return HasMany<EventParticipant, $this>
     */
    public function participants(): HasMany
    {
        return $this->hasMany(EventParticipant::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePlanned(Builder $query): Builder
    {
        return $query->where('status', EventStatus::Planned);
    }
}
