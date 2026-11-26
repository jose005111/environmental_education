<?php

namespace App\Models;

use App\Enums\RoleName;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property bool $is_active
 * @property-read Role|null $role
 * @property-read Zone|null $zone
 */
#[Fillable(['name', 'email', 'password', 'role_id', 'zone_id', 'phone', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmailContract
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, MustVerifyEmail, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function hasRole(RoleName $role): bool
    {
        return $this->role?->name === $role;
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(RoleName::ADMINISTRADOR);
    }

    public function isModerator(): bool
    {
        return $this->hasRole(RoleName::MODERADOR);
    }

    public function isResident(): bool
    {
        return $this->hasRole(RoleName::RESIDENTE);
    }

    /** @return BelongsTo<Role, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function contents(): HasMany
    {
        return $this->hasMany(Content::class, 'author_id');
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class, 'author_id');
    }

    public function quizAttempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class, 'author_id');
    }

    public function activityParticipations(): HasMany
    {
        return $this->hasMany(ActivityParticipation::class);
    }

    public function participatedActivities(): BelongsToMany
    {
        return $this->belongsToMany(Activity::class, 'activity_participations')
            ->using(ActivityParticipation::class)
            ->withPivot(['id', 'status', 'notes', 'proof_photo', 'submitted_at', 'reviewed_by', 'reviewed_at', 'review_note'])
            ->withTimestamps();
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function assignedReports(): HasMany
    {
        return $this->hasMany(Report::class, 'assigned_to');
    }

    public function reportUpdates(): HasMany
    {
        return $this->hasMany(ReportUpdate::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'author_id');
    }

    public function eventRegistrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    public function registeredEvents(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_registrations')
            ->using(EventRegistration::class)
            ->withPivot(['id', 'status'])
            ->withTimestamps();
    }

    public function reviewedParticipations(): HasMany
    {
        return $this->hasMany(ActivityParticipation::class, 'reviewed_by');
    }
}
