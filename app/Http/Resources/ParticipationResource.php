<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ParticipationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $photo = $this->proof_photo;

        return [
            'id' => $this->id,
            'activity_id' => $this->activity_id,
            'user_id' => $this->user_id,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'notes' => $this->notes,
            'proof_photo' => $photo,
            'proof_photo_url' => $photo === null
                ? null
                : Storage::disk(config('uploads.disk', 'public'))->url($photo),
            'submitted_at' => $this->submitted_at?->toJSON(),
            'reviewed_by' => $this->reviewed_by,
            'reviewed_at' => $this->reviewed_at?->toJSON(),
            'review_note' => $this->review_note,
            'activity' => $this->whenLoaded('activity', fn () => new ActivitySummaryResource($this->activity)),
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
            ]),
            'reviewer' => $this->whenLoaded('reviewer', fn () => $this->reviewer === null ? null : [
                'id' => $this->reviewer->id,
                'name' => $this->reviewer->name,
            ]),
            'created_at' => $this->created_at?->toJSON(),
        ];
    }
}
