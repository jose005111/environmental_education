<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

final class StoredFile
{
    public function store(UploadedFile $file, string $directory): array
    {
        $directories = config('uploads.directories', []);

        if (! array_key_exists($directory, $directories)) {
            throw new InvalidArgumentException("Directório de upload inválido: {$directory}");
        }

        Validator::make(
            ['file' => $file],
            [
                'file' => [
                    'required',
                    'file',
                    'mimetypes:'.implode(',', config('uploads.mimetypes', [])),
                    'max:'.config('uploads.max_size_kb', 5120),
                ],
            ],
            ['file.mimetypes' => 'A imagem deve ser JPEG, PNG ou WebP.', 'file.max' => 'A imagem não pode exceder 5 MB.'],
        )->validate();

        $disk = config('uploads.disk', 'public');
        $path = $file->store($directories[$directory], ['disk' => $disk]);

        return [
            'path' => $path,
            'url' => Storage::disk($disk)->url($path),
        ];
    }

    public function delete(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        $disk = Storage::disk(config('uploads.disk', 'public'));

        if ($disk->exists($path)) {
            $disk->delete($path);
        }
    }
}
