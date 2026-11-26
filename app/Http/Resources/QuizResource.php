<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuizResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $canManage = $request->user()?->isModerator() || $request->user()?->isAdmin();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status?->value,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category?->id,
                'name' => $this->category?->name,
            ]),
            'questions' => $this->whenLoaded('questions', fn () => $this->questions->map(fn ($question) => [
                'id' => $question->id,
                'statement' => $question->statement,
                ...($canManage ? ['explanation' => $question->explanation] : []),
                'position' => $question->position,
                'options' => $question->relationLoaded('options')
                    ? $question->options->map(fn ($option) => [
                        'id' => $option->id,
                        'text' => $option->text,
                        ...($canManage ? ['is_correct' => $option->is_correct] : []),
                        'position' => $option->position,
                    ])
                    : [],
            ])),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
