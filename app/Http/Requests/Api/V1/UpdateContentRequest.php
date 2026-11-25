<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\ContentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'required', 'integer', Rule::exists('categories', 'id')],
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'summary' => ['sometimes', 'required', 'string', 'max:300'],
            'body' => ['sometimes', 'nullable', 'string'],
            'type' => ['sometimes', 'required', Rule::enum(ContentType::class)],
            'cover_image' => ['sometimes', 'nullable', 'file', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120'],
            'media_url' => ['sometimes', 'nullable', 'url', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.exists' => 'A categoria indicada não existe.',
            'title.max' => 'O título não pode exceder 200 caracteres.',
            'summary.max' => 'O resumo não pode exceder 300 caracteres.',
            'cover_image.image' => 'A capa deve ser uma imagem.',
            'cover_image.mimetypes' => 'A capa deve ser JPEG, PNG ou WebP.',
            'cover_image.max' => 'A capa não pode exceder 5 MB.',
            'media_url.url' => 'O URL de média não é válido.',
        ];
    }
}
