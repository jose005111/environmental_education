<?php

namespace App\Models;

use App\Enums\ReportStatus;
use Carbon\Carbon;
use Database\Factories\ReportFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property ReportStatus $status
 * @property Carbon|null $resolved_at
 */
class Report extends Model
{
    /** @use HasFactory<ReportFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'zone_id',
        'category_id',
        'assigned_to',
        'title',
        'description',
        'location_note',
        'latitude',
        'longitude',
        'status',
        'is_public',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'status' => ReportStatus::class,
            'is_public' => 'boolean',
            'resolved_at' => 'datetime',
        ];
    }

    public function scopePublic(Builder $query): void
    {
        $query->where('status', ReportStatus::RESOLVIDO)
            ->where('is_public', true);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ReportPhoto::class);
    }

    public function updates(): HasMany
    {
        return $this->hasMany(ReportUpdate::class);
    }
}
