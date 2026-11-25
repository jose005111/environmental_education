<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class ActivityResource extends ActivitySummaryResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'category_id' => $this->category_id,
            'author_id' => $this->author_id,
            'instructions' => $this->instructions,
            'participations_count' => $this->whenCounted('participations'),
            'author' => $this->whenLoaded('author', fn () => [
                'id' => $this->author?->id,
                'name' => $this->author?->name,
            ]),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
