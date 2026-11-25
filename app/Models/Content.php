<?php

namespace App\Models;

use App\Enums\ContentStatus;
use App\Enums\ContentType;
use App\Models\Concerns\DeletesStoredFiles;
use App\Models\Concerns\HasSlug;
use Database\Factories\ContentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property ContentStatus $status
 * @property \Carbon\Carbon|null $published_at
 */
class Content extends Model
{
    /** @use HasFactory<ContentFactory> */
    use DeletesStoredFiles, HasFactory, HasSlug, SoftDeletes;

    protected $fillable = [
        'category_id',
        'author_id',
        'title',
        'slug',
        'summary',
        'body',
        'type',
        'cover_image',
        'media_url',
        'status',
        'published_at',
        'views_count',
    ];

    protected function casts(): array
    {
        return [
            'type' => ContentType::class,
            'status' => ContentStatus::class,
            'published_at' => 'datetime',
            'views_count' => 'integer',
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
        $query->where('status', ContentStatus::PUBLICADO)
            ->whereNotNull('published_at');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
