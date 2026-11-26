<?php

namespace App\Models;

use App\Enums\ActivityStatus;
use App\Models\Concerns\HasSlug;
use Carbon\Carbon;
use Database\Factories\ActivityFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $slug
 * @property ActivityStatus $status
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 * @property-read Category|null $category
 * @property-read User|null $author
 */
class Activity extends Model
{
    /** @use HasFactory<ActivityFactory> */
    use HasFactory, HasSlug, SoftDeletes;

    protected $fillable = [
        'category_id',
        'author_id',
        'title',
        'slug',
        'summary',
        'instructions',
        'starts_on',
        'ends_on',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'status' => ActivityStatus::class,
        ];
    }

    protected function slugSource(): string
    {
        return $this->title;
    }

    public function scopeOpen(Builder $query): void
    {
        $query->where('status', ActivityStatus::ABERTA)
            ->where(fn (Builder $query) => $query->whereNull('starts_on')->orWhere('starts_on', '<=', now()->toDateString()))
            ->where(fn (Builder $query) => $query->whereNull('ends_on')->orWhere('ends_on', '>=', now()->toDateString()));
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function participations(): HasMany
    {
        return $this->hasMany(ActivityParticipation::class);
    }

    /** @return HasMany<ActivityPhoto, $this> */
    public function photos(): HasMany
    {
        return $this->hasMany(ActivityPhoto::class)->orderBy('id');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'activity_participations')
            ->using(ActivityParticipation::class)
            ->withPivot(['id', 'status', 'notes', 'proof_photo', 'submitted_at', 'reviewed_by', 'reviewed_at', 'review_note'])
            ->withTimestamps();
    }
}
