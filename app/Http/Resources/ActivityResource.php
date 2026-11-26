<?php

namespace App\Http\Resources;

use App\Models\ActivityParticipation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ActivityResource extends ActivitySummaryResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'category_id' => $this->category_id,
            'author_id' => $this->author_id,
            'instructions' => $this->instructions,
            'photos' => $this->whenLoaded('photos', fn () => $this->photos->map(fn ($photo) => [
                'id' => $photo->id,
                'url' => Storage::disk(config('uploads.disk', 'public'))->url($photo->path),
            ])->all()),
            'participations_count' => $this->whenCounted('participations'),
            'my_participation_status' => $request->user()
                ? ActivityParticipation::query()
                    ->where('activity_id', $this->id)
                    ->where('user_id', $request->user()->getKey())
                    ->first()?->status?->value
                : null,
            'author' => $this->whenLoaded('author', fn () => [
                'id' => $this->author?->id,
                'name' => $this->author?->name,
            ]),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
