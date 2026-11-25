<?php

namespace App\Models\Concerns;

use App\Services\StoredFile;

trait DeletesStoredFiles
{
    protected function storedFileColumns(): array
    {
        return [];
    }

    public static function bootDeletesStoredFiles(): void
    {
        static::deleted(function (self $model): void {
            foreach ($model->storedFileColumns() as $column) {
                app(StoredFile::class)->delete($model->getAttribute($column));
            }
        });
    }
}
