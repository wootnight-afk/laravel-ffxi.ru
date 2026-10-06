<?php

namespace App\Models;

use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property UserStatus $status
 * @property Carbon|null $banned_until
 * @property Carbon|null $chat_banned_until
 * @property bool $chat_banned_permanently
 * @property Carbon|null $deletion_requested_at
 * @property Carbon|null $suspended_at
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $last_seen_at
 * @property Carbon|null $last_activity_seen_at
 * @property Carbon|null $pd_consent_at
 * @property Carbon|null $marketing_consent_at
 * @property string|null $app_authentication_secret
 * @property array<string>|null $app_authentication_recovery_codes
 */
#[Fillable([
    'name', 'email', 'password',
    'rank_id', 'phone', 'phone_is_public', 'avatar_path',
    'is_profile_public', 'race', 'main_job', 'legend', 'legend_html',
    'last_seen_at', 'last_activity_seen_at',
    'chat_banned_until', 'chat_banned_permanently', 'banned_until', 'ban_reason',
    'status', 'deletion_requested_at', 'deletion_reason', 'suspended_at', 'suspension_reason',
    'pd_consent_at', 'pd_policy_version', 'marketing_consent_at',
])]
#[Hidden(['password', 'remember_token', 'app_authentication_secret', 'app_authentication_recovery_codes'])]
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, InteractsWithAppAuthentication, InteractsWithAppAuthenticationRecovery, Notifiable, SoftDeletes;

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
            'chat_banned_permanently' => 'boolean',
            'banned_until' => 'datetime',
            'deletion_requested_at' => 'datetime',
            'suspended_at' => 'datetime',
            'pd_consent_at' => 'datetime',
            'marketing_consent_at' => 'datetime',
            'status' => UserStatus::class,
        ];
    }

    /**
     * Only admins and editors may access the admin panel.
     * Banned and deletion-requested accounts are always blocked.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if ($this->isBanned() || $this->isDeletionRequested() || $this->isSuspended()) {
            return false;
        }

        return $this->hasAnyRole(['admin', 'editor']);
    }

    /**
     * @return BelongsTo<UserRank, $this>
     */
    public function rank(): BelongsTo
    {
        return $this->belongsTo(UserRank::class);
    }

    /**
     * @return HasMany<UserSocialLink, $this>
     */
    public function socialLinks(): HasMany
    {
        return $this->hasMany(UserSocialLink::class)->orderBy('sort_order');
    }

    /**
     * @return HasOne<GuestVisitor, $this>
     */
    public function guestVisitor(): HasOne
    {
        return $this->hasOne(GuestVisitor::class, 'converted_user_id');
    }

    /**
     * @return HasMany<News, $this>
     */
    public function news(): HasMany
    {
        return $this->hasMany(News::class);
    }

    /**
     * @return HasMany<Comment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * @return HasMany<Event, $this>
     */
    public function eventsAsLeader(): HasMany
    {
        return $this->hasMany(Event::class, 'user_id');
    }

    /**
     * @return HasMany<EventParticipant, $this>
     */
    public function eventParticipations(): HasMany
    {
        return $this->hasMany(EventParticipant::class);
    }

    /**
     * @return HasMany<ChatMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
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
        return $this->chat_banned_permanently
            || ($this->chat_banned_until !== null && $this->chat_banned_until->isFuture());
    }

    public function isDeletionRequested(): bool
    {
        return $this->status === UserStatus::DeletionRequested;
    }

    public function isSuspended(): bool
    {
        return $this->status === UserStatus::Suspended;
    }

    public function isProfilePublic(): bool
    {
        return $this->is_profile_public && ! $this->isDeletionRequested() && ! $this->isSuspended();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', UserStatus::Active);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeNotBanned(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('banned_until')
                ->orWhere('banned_until', '<=', now());
        });
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePublicProfile(Builder $query): Builder
    {
        return $query->where('is_profile_public', true)
            ->where('status', UserStatus::Active);
    }
}
