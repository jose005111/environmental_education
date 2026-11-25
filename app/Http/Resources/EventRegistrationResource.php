<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventRegistrationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'user_id' => $this->user_id,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'user' => $this->whenLoaded('user', fn () => new UserSummaryResource($this->user)),
            'event' => $this->whenLoaded('event', fn () => new EventSummaryResource($this->event)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
