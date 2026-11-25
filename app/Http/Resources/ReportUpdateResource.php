<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportUpdateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $privileged = $request->user() !== null
            && ($request->user()->isModerator() || $request->user()->isAdmin());

        return [
            'id' => $this->id,
            'from_status' => $this->from_status?->value,
            'from_status_label' => $this->from_status?->label(),
            'to_status' => $this->to_status?->value,
            'to_status_label' => $this->to_status?->label(),
            'note' => $this->when($privileged, fn () => $this->note),
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
            ]),
            'created_at' => $this->created_at?->toJSON(),
        ];
    }
}
