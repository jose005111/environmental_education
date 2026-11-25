<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $privileged = $user !== null && ($user->isModerator() || $user->isAdmin());
        $owner = $user !== null && $user->getKey() === $this->user_id;

        return [
            'id' => $this->id,
            'user_id' => $this->when($privileged || $owner, fn () => $this->user_id),
            'zone_id' => $this->zone_id,
            'category_id' => $this->category_id,
            'assigned_to' => $this->when($privileged, fn () => $this->assigned_to),
            'title' => $this->title,
            'description' => $this->description,
            'location_note' => $this->location_note,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'is_public' => $this->is_public,
            'latitude' => $this->when($privileged, fn () => $this->latitude),
            'longitude' => $this->when($privileged, fn () => $this->longitude),
            'phone' => $this->when($privileged, fn () => $this->user?->phone),
            'resolved_at' => $this->resolved_at?->toJSON(),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
            'user' => $this->when(
                ($privileged || $owner) && $this->resource->relationLoaded('user'),
                fn () => [
                    'id' => $this->user?->id,
                    'name' => $this->user?->name,
                    'email' => $this->when($privileged, fn () => $this->user?->email),
                    'phone' => $this->when($privileged, fn () => $this->user?->phone),
                ],
            ),
            'zone' => $this->whenLoaded('zone', fn () => [
                'id' => $this->zone?->id,
                'name' => $this->zone?->name,
            ]),
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category?->id,
                'name' => $this->category?->name,
            ]),
            'assignee' => $this->whenLoaded('assignee', fn () => [
                'id' => $this->assignee?->id,
                'name' => $this->assignee?->name,
            ]),
            'photos' => $this->whenLoaded('photos', fn () => $this->photos->map(fn ($photo) => [
                'id' => $photo->id,
                'path' => $photo->path,
                'url' => Storage::disk(config('uploads.disk', 'public'))->url($photo->path),
            ])->values()->all()),
            'updates' => $this->when(
                ($privileged || $owner) && $this->resource->relationLoaded('updates'),
                fn () => ReportUpdateResource::collection($this->updates),
            ),
        ];
    }
}
