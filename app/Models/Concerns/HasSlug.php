<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

trait HasSlug
{
    abstract protected function slugSource(): string;

    protected static function bootHasSlug(): void
    {
        static::creating(function (self $model): void {
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

    protected static function slugExists(string $slug): bool
    {
        $query = static::query();

        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            $query->withoutGlobalScope(SoftDeletingScope::class);
        }

        return $query->where('slug', $slug)->exists();
    }
}
