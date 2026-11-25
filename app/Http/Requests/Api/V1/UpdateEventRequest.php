<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\EventStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->hasFile('cover') && $this->hasFile('cover_image')) {
            $this->files->set('cover', $this->file('cover_image'));
            $this->files->remove('cover_image');
        }
    }

    public function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'zone_id' => ['sometimes', 'nullable', 'integer', 'exists:zones,id'],
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'description' => ['sometimes', 'required', 'string'],
            'location' => ['sometimes', 'required', 'string', 'max:255'],
            'starts_at' => ['sometimes', 'required', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_at'],
            'capacity' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:65535'],
            'cover' => [
                'sometimes',
                'nullable',
                'file',
                'mimetypes:'.implode(',', config('uploads.mimetypes', [])),
                'max:'.config('uploads.max_size_kb', 5120),
            ],
            'cover_image' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::enum(EventStatus::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'O título do evento é obrigatório.',
            'description.required' => 'A descrição do evento é obrigatória.',
            'location.required' => 'O local do evento é obrigatório.',
            'starts_at.required' => 'A data de início é obrigatória.',
            'starts_at.date' => 'A data de início deve ser uma data válida.',
            'ends_at.date' => 'A data de fim deve ser uma data válida.',
            'ends_at.after_or_equal' => 'A data de fim não pode ser anterior ao início.',
            'capacity.integer' => 'A capacidade deve ser um número inteiro.',
            'capacity.max' => 'A capacidade não pode exceder 65535.',
            'cover.file' => 'A capa deve ser um ficheiro.',
            'cover.mimetypes' => 'A capa deve ser JPEG, PNG ou WebP.',
            'cover.max' => 'A capa não pode exceder 5 MB.',
        ];
    }
}
