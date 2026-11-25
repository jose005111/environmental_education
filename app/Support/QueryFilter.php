<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class QueryFilter
{
    private array $filters;

    private array $sorts;

    private array $search;

    public function __construct(
        private readonly Request $request,
        array $filters = [],
        array $sorts = [],
        array $search = [],
        private readonly string $defaultSort = '-created_at',
    ) {
        $this->filters = $filters;
        $this->sorts = $sorts;
        $this->search = $search;
    }

    public function apply(Builder $query): Builder
    {
        $this->applyFilters($query);
        $this->applySearch($query);
        $this->applySort($query);

        return $query;
    }

    public function perPage(int $default = 15, int $max = 50): int
    {
        return min(max((int) $this->request->input('per_page', $default), 1), $max);
    }

    private function applyFilters(Builder $query): void
    {
        $filters = $this->request->input('filter', []);

        if (! is_array($filters)) {
            return;
        }

        foreach ($filters as $field => $value) {
            if (! array_key_exists($field, $this->filters) || blank($value)) {
                continue;
            }

            $definition = $this->filters[$field];

            if (is_callable($definition)) {
                $definition($query, $value);

                continue;
            }

            $column = is_array($definition) ? ($definition['column'] ?? $field) : $definition;
            $operator = is_array($definition) ? ($definition['operator'] ?? '=') : '=';

            if ($operator === 'like') {
                $needle = is_array($value) ? implode(',', $value) : (string) $value;
                $query->where($column, 'like', '%'.$this->escapeLike($needle).'%');

                continue;
            }

            $values = self::parseValues($value);

            if ($values !== []) {
                $query->whereIn($column, $values);

                continue;
            }

            $query->whereRaw('1 = 0');
        }
    }

    private function applySearch(Builder $query): void
    {
        $search = trim((string) $this->request->input('search', ''));

        if ($search === '' || $this->search === []) {
            return;
        }

        $query->where(function (Builder $builder) use ($search): void {
            foreach ($this->search as $column) {
                $builder->orWhere($column, 'like', '%'.$this->escapeLike($search).'%');
            }
        });
    }

    private function applySort(Builder $query): void
    {
        $sort = (string) $this->request->input('sort', $this->defaultSort);
        $descending = str_starts_with($sort, '-');
        $field = ltrim($sort, '-');

        if (! array_key_exists($field, $this->sorts)) {
            $descending = str_starts_with($this->defaultSort, '-');
            $field = ltrim($this->defaultSort, '-');
        }

        $column = $this->sorts[$field] ?? 'created_at';

        $query->orderBy($column, $descending ? 'desc' : 'asc');
    }

    public static function parseValues(mixed $value): array
    {
        $values = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_filter(
            array_map(static fn (mixed $item): string => trim((string) $item), $values),
            static fn (string $item): bool => $item !== '',
        ));
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
