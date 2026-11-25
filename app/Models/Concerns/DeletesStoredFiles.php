<?php

namespace App\Models\Concerns;

use App\Services\StoredFile;
use Illuminate\Database\Eloquent\Model;

trait DeletesStoredFiles
{
    protected function storedFileColumns(): array
    {
        return [];
    }

    public static function bootDeletesStoredFiles(): void
    {
        static::deleted(function (Model $model): void {
            foreach ($model->storedFileColumns() as $column) {
                app(StoredFile::class)->delete($model->getAttribute($column));
            }
        });
    }
}
