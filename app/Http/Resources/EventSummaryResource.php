<?php

namespace App\Http\Resources;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Event */
class EventSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $active = (int) ($this->active_registrations_count ?? 0);

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'location' => $this->location,
            'starts_at' => $this->starts_at?->toJSON(),
            'ends_at' => $this->ends_at?->toJSON(),
            'capacity' => $this->capacity,
            'vagas_livres' => $this->capacity === null ? null : max(0, $this->capacity - $active),
            'active_registrations_count' => $this->whenCounted('active_registrations', $active),
            'cover_image' => $this->cover_image,
            'category' => $this->whenLoaded('category', fn () => new CategorySummaryResource($this->category)),
            'zone' => $this->whenLoaded('zone', fn () => new ZoneSummaryResource($this->zone)),
        ];
    }
}
