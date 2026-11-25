<?php

namespace App\Models;

use App\Enums\ParticipationStatus;
use App\Models\Concerns\DeletesStoredFiles;
use Database\Factories\ActivityParticipationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ActivityParticipation extends Pivot
{
    /** @use HasFactory<ActivityParticipationFactory> */
    use DeletesStoredFiles, HasFactory;

    protected $table = 'activity_participations';

    public $incrementing = true;

    protected $fillable = [
        'activity_id',
        'user_id',
        'status',
        'notes',
        'proof_photo',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'review_note',
    ];

    protected function casts(): array
    {
        return [
            'status' => ParticipationStatus::class,
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    protected function storedFileColumns(): array
    {
        return ['proof_photo'];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
