<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\ActivityStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'required', 'integer', 'exists:categories,id'],
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'summary' => ['sometimes', 'required', 'string', 'max:300'],
            'instructions' => ['sometimes', 'required', 'string'],
            'starts_on' => ['sometimes', 'nullable', 'date'],
            'ends_on' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_on'],
            'status' => ['sometimes', 'required', Rule::enum(ActivityStatus::class)],
            'photos' => ['sometimes', 'array', 'max:5'],
            'photos.*' => ['required', 'file', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120'],
            'removed_photo_ids' => ['sometimes', 'array'],
            'removed_photo_ids.*' => ['integer', 'distinct', Rule::exists('activity_photos', 'id')->where('activity_id', $this->route('activity')?->id)],
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.exists' => 'A categoria indicada não existe.',
            'starts_on.date' => 'A data de início deve ser uma data válida.',
            'ends_on.date' => 'A data de fim deve ser uma data válida.',
            'ends_on.after_or_equal' => 'A data de fim não pode ser anterior à data de início.',
            'status.enum' => 'O estado da actividade é inválido.',
            'photos.max' => 'A actividade pode ter no máximo 5 fotografias.',
        ];
    }
}
