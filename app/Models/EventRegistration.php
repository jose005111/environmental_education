<?php

namespace App\Models;

use App\Enums\RegistrationStatus;
use Database\Factories\EventRegistrationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property RegistrationStatus $status
 */
class EventRegistration extends Pivot
{
    /** @use HasFactory<EventRegistrationFactory> */
    use HasFactory;

    protected $table = 'event_registrations';

    public $incrementing = true;

    protected $fillable = ['event_id', 'user_id', 'status'];

    protected function casts(): array
    {
        return ['status' => RegistrationStatus::class];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
