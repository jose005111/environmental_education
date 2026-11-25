<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

trait HasSlug
{
    abstract protected function slugSource(): string;

    protected static function bootHasSlug(): void
    {
        static::creating(function (Model $model): void {
            if (filled($model->slug)) {
                return;
            }

            $base = Str::slug($model->slugSource());
            $slug = $base;
            $suffix = 2;

            while (static::slugExists($slug)) {
                $slug = $base.'-'.$suffix;
                $suffix++;
            }

            $model->slug = $slug;
        });
    }

    private static function slugExists(string $slug): bool
    {
        $query = static::query();

        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            $query->withTrashed();
        }

        return $query->where('slug', $slug)->exists();
    }
}
