<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'contents_count' => $this->whenCounted('contents'),
            'contents' => $this->whenLoaded('contents', fn () => $this->contents->map(fn ($content) => [
                'id' => $content->id,
                'title' => $content->title,
                'slug' => $content->slug,
            ])),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
