<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $active = (int) ($this->active_registrations_count ?? 0);

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'location' => $this->location,
            'starts_at' => $this->starts_at?->toJSON(),
            'ends_at' => $this->ends_at?->toJSON(),
            'capacity' => $this->capacity,
            'vagas_livres' => $this->capacity === null ? null : max(0, $this->capacity - $active),
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'cover_image' => $this->cover_image,
            'category_id' => $this->category_id,
            'zone_id' => $this->zone_id,
            'category' => $this->whenLoaded('category', fn () => new CategorySummaryResource($this->category)),
            'zone' => $this->whenLoaded('zone', fn () => new ZoneSummaryResource($this->zone)),
            'author' => $this->whenLoaded('author', fn () => new AuthorSummaryResource($this->author)),
            'registrations_count' => $this->whenCounted('registrations'),
            'active_registrations_count' => $this->whenCounted('active_registrations', $active),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
