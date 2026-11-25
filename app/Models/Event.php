<?php

namespace App\Models;

use App\Enums\EventStatus;
use App\Models\Concerns\DeletesStoredFiles;
use App\Models\Concerns\HasSlug;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property EventStatus $status
 * @property \Carbon\Carbon $starts_at
 * @property \Carbon\Carbon $ends_at
 */
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use DeletesStoredFiles, HasFactory, HasSlug, SoftDeletes;

    protected $fillable = [
        'category_id',
        'zone_id',
        'author_id',
        'title',
        'slug',
        'description',
        'location',
        'starts_at',
        'ends_at',
        'capacity',
        'cover_image',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'capacity' => 'integer',
            'status' => EventStatus::class,
        ];
    }

    protected function slugSource(): string
    {
        return $this->title;
    }

    protected function storedFileColumns(): array
    {
        return ['cover_image'];
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('status', EventStatus::PUBLICADO);
    }

    public function scopePublic(Builder $query): void
    {
        $query->whereIn('status', [
            EventStatus::PUBLICADO,
            EventStatus::CANCELADO,
            EventStatus::CONCLUIDO,
        ]);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    public function registrants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_registrations')
            ->using(EventRegistration::class)
            ->withPivot(['id', 'status'])
            ->withTimestamps();
    }
}
