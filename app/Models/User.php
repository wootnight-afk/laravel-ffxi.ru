<?php

namespace App\Models;

use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable([
    'name', 'email', 'password',
    'rank_id', 'phone', 'phone_is_public', 'avatar_path',
    'is_profile_public', 'race', 'main_job', 'legend', 'legend_html',
    'last_seen_at', 'last_activity_seen_at',
    'chat_banned_until', 'banned_until', 'ban_reason',
    'status', 'deletion_requested_at', 'deletion_reason',
    'pd_consent_at', 'pd_policy_version', 'marketing_consent_at',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'phone' => 'encrypted',
            'phone_is_public' => 'boolean',
            'is_profile_public' => 'boolean',
            'last_seen_at' => 'datetime',
            'last_activity_seen_at' => 'datetime',
            'chat_banned_until' => 'datetime',
            'banned_until' => 'datetime',
            'deletion_requested_at' => 'datetime',
            'pd_consent_at' => 'datetime',
            'marketing_consent_at' => 'datetime',
            'status' => UserStatus::class,
        ];
    }

    public function rank(): BelongsTo
    {
        return $this->belongsTo(UserRank::class);
    }

    public function socialLinks(): HasMany
    {
        return $this->hasMany(UserSocialLink::class)->orderBy('sort_order');
    }

    public function guestVisitor(): HasOne
    {
        return $this->hasOne(GuestVisitor::class, 'converted_user_id');
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function isEditor(): bool
    {
        return $this->hasRole('editor');
    }

    public function isBanned(): bool
    {
        return $this->banned_until !== null && $this->banned_until->isFuture();
    }

    public function isChatBanned(): bool
    {
        return $this->chat_banned_until !== null && $this->chat_banned_until->isFuture();
    }

    public function isDeletionRequested(): bool
    {
        return $this->status === UserStatus::DeletionRequested;
    }

    public function isProfilePublic(): bool
    {
        return $this->is_profile_public && ! $this->isDeletionRequested();
    }

    public function scopeActive($query)
    {
        return $query->where('status', UserStatus::Active);
    }

    public function scopeNotBanned($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('banned_until')
                ->orWhere('banned_until', '<=', now());
        });
    }

    public function scopePublicProfile($query)
    {
        return $query->where('is_profile_public', true)
            ->where('status', UserStatus::Active);
    }
}
