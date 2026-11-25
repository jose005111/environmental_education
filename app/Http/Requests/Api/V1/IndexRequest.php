<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class IndexRequest extends FormRequest
{
    private const ALLOWED = [
        'api.v1.contents.index' => [
            'filters' => ['category', 'type'],
            'sorts' => ['title', 'published_at', 'views_count', 'created_at'],
        ],
        'api.v1.activities.index' => [
            'filters' => ['category', 'status'],
            'sorts' => ['title', 'starts_on', 'created_at'],
        ],
        'api.v1.activities.participations.index' => [
            'filters' => ['status', 'activity', 'user'],
            'sorts' => ['submitted_at', 'created_at'],
        ],
        'api.v1.activities.me.participations' => [
            'filters' => ['status'],
            'sorts' => ['submitted_at', 'created_at'],
        ],
        'api.v1.events.index' => [
            'filters' => ['status', 'category', 'zone', 'title'],
            'sorts' => ['starts_at', 'ends_at', 'title', 'created_at'],
        ],
        'api.v1.events.registrations' => [
            'filters' => ['status', 'user'],
            'sorts' => ['status', 'created_at'],
        ],
        'api.v1.events.my-registrations' => [
            'filters' => ['status', 'event'],
            'sorts' => ['status', 'created_at'],
        ],
        'api.v1.quizzes.index' => [
            'filters' => ['status', 'category_id', 'category'],
            'sorts' => ['title', 'status', 'created_at'],
        ],
        'api.v1.quizzes.attempts.index' => [
            'filters' => ['quiz_id'],
            'sorts' => ['created_at', 'completed_at'],
        ],
        'api.v1.reports.public' => [
            'filters' => ['zone', 'zone_id', 'category', 'category_id', 'status'],
            'sorts' => ['title', 'status', 'created_at', 'updated_at', 'resolved_at'],
        ],
        'api.v1.reports.mine' => [
            'filters' => ['zone', 'zone_id', 'category', 'category_id', 'status'],
            'sorts' => ['title', 'status', 'created_at', 'updated_at'],
        ],
        'api.v1.reports.index' => [
            'filters' => ['zone', 'zone_id', 'status', 'assigned', 'assigned_to', 'category', 'category_id'],
            'sorts' => ['title', 'status', 'created_at', 'updated_at', 'resolved_at'],
        ],
        'api.v1.categories.index' => [
            'filters' => ['name', 'slug', 'category'],
            'sorts' => ['name', 'slug', 'created_at'],
        ],
        'api.v1.zones.index' => [
            'filters' => ['name'],
            'sorts' => ['name', 'created_at'],
        ],
        'api.v1.users.index' => [
            'filters' => ['role', 'zone', 'is_active'],
            'sorts' => ['name', 'email', 'created_at'],
        ],
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'sort' => ['sometimes', 'string', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'filter' => ['sometimes', 'array'],
            'filter.*' => ['nullable'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $allowed = self::ALLOWED[$this->route()?->getName() ?? ''] ?? null;

        if ($allowed === null) {
            return;
        }

        $validator->after(function (Validator $validator) use ($allowed): void {
            foreach (array_keys((array) $this->input('filter', [])) as $filter) {
                if (! is_string($filter) || in_array($filter, $allowed['filters'], true)) {
                    continue;
                }

                $validator->errors()->add("filter.{$filter}", 'Filtro não suportado nesta listagem.');
            }

            $sort = $this->input('sort');

            if (is_string($sort) && $sort !== '' && ! in_array(ltrim($sort, '-'), $allowed['sorts'], true)) {
                $validator->errors()->add('sort', 'Ordenação não suportada nesta listagem.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'per_page.integer' => 'O campo por página deve ser um número inteiro.',
            'per_page.min' => 'O mínimo de registos por página é 1.',
            'per_page.max' => 'O máximo de registos por página é 50.',
            'sort.string' => 'A ordenação deve ser um texto.',
            'search.string' => 'A pesquisa deve ser um texto.',
            'filter.array' => 'Os filtros devem ser um objeto.',
        ];
    }
}
