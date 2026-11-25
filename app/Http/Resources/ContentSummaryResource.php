<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ContentSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'summary' => $this->summary,
            'type' => $this->type?->value,
            'status' => $this->status?->value,
            'cover_image' => $this->cover_image
                ? Storage::disk(config('uploads.disk', 'public'))->url($this->cover_image)
                : null,
            'published_at' => $this->published_at?->toJSON(),
            'views_count' => $this->views_count,
            'created_at' => $this->created_at?->toJSON(),
        ];
    }
}
