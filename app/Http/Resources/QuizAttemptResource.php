<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuizAttemptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'quiz_id' => $this->quiz_id,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
            ]),
            'quiz' => $this->whenLoaded('quiz', fn () => [
                'id' => $this->quiz?->id,
                'title' => $this->quiz?->title,
            ]),
            'total_questions' => $this->total_questions,
            'correct_count' => $this->correct_count,
            'completed_at' => $this->completed_at?->toJSON(),
            'answers' => $this->whenLoaded('answers', fn () => $this->answers->map(fn ($answer) => [
                'question_id' => $answer->quiz_question_id,
                'statement' => $answer->question?->statement,
                'explanation' => $answer->question?->explanation,
                'selected_option_id' => $answer->quiz_option_id,
                'selected_option' => $answer->option?->text,
                'is_correct' => $answer->is_correct,
            ])),
            'created_at' => $this->created_at?->toJSON(),
        ];
    }
}
