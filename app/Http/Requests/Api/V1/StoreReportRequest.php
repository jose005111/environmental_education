<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Report;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('create', Report::class);

        return true;
    }

    public function rules(): array
    {
        $limit = (int) config('uploads.report_photos_limit', 5);
        $maxSize = (int) config('uploads.max_size_kb', 5120);
        $mimetypes = implode(',', config('uploads.mimetypes', ['image/jpeg', 'image/png', 'image/webp']));

        return [
            'zone_id' => ['required', 'integer', 'exists:zones,id'],
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string'],
            'location_note' => ['sometimes', 'nullable', 'string', 'max:255'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'photos' => ['sometimes', 'array', 'max:'.$limit],
            'photos.*' => ['file', 'mimetypes:'.$mimetypes, 'max:'.$maxSize],
        ];
    }

    public function messages(): array
    {
        return [
            'zone_id.required' => 'A zona do reporte é obrigatória.',
            'zone_id.exists' => 'A zona indicada não existe.',
            'category_id.exists' => 'A categoria indicada não existe.',
            'title.required' => 'O título do reporte é obrigatório.',
            'title.max' => 'O título não pode exceder 200 caracteres.',
            'description.required' => 'A descrição do reporte é obrigatória.',
            'photos.max' => 'O número máximo de fotografias foi excedido.',
            'photos.*.mimetypes' => 'As fotografias devem ser JPEG, PNG ou WebP.',
            'photos.*.max' => 'Cada fotografia não pode exceder 5 MB.',
        ];
    }
}
