<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class SubmitActivityProofRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'proof_photo' => [
                'required',
                'file',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:5120',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'notes.max' => 'O relato não pode exceder 1000 caracteres.',
            'proof_photo.required' => 'A foto de prova é obrigatória.',
            'proof_photo.file' => 'A foto de prova deve ser um ficheiro.',
            'proof_photo.mimetypes' => 'A imagem deve ser JPEG, PNG ou WebP.',
            'proof_photo.max' => 'A imagem não pode exceder 5 MB.',
        ];
    }
}
