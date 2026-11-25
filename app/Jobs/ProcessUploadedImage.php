<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class ProcessUploadedImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $path,
        public readonly string $disk,
    ) {}

    public function handle(): void
    {
        $storage = Storage::disk($this->disk);

        if (! $storage->exists($this->path)) {
            return;
        }

        $contents = $storage->get($this->path);

        if ($contents === null) {
            return;
        }

        $size = @getimagesizefromstring($contents);

        if ($size === false || ! function_exists('imagecreatefromstring') || ! function_exists('imagewebp')) {
            return;
        }

        $image = imagecreatefromstring($contents);

        if ($image === false) {
            return;
        }

        if (function_exists('imagepalettetotruecolor')) {
            imagepalettetotruecolor($image);
        }

        $maxDimension = max(1, (int) config('uploads.thumbnail_max_dimension', 1200));
        $ratio = min(1, $maxDimension / max($size[0], $size[1]));
        $width = max(1, (int) round($size[0] * $ratio));
        $height = max(1, (int) round($size[1] * $ratio));

        $resized = imagescale($image, $width, $height);

        if ($resized === false) {
            imagedestroy($image);

            return;
        }

        ob_start();
        $written = imagewebp($resized, null, 80);
        $webp = ob_get_clean();
        imagedestroy($resized);
        imagedestroy($image);

        if ($written !== true || ! is_string($webp)) {
            return;
        }

        $storage->put($this->thumbnailPath(), $webp, 'public');
    }

    private function thumbnailPath(): string
    {
        $extension = pathinfo($this->path, PATHINFO_EXTENSION);
        $basePath = $extension === '' ? $this->path : Str::beforeLast($this->path, '.'.$extension);

        return "{$basePath}-thumb.webp";
    }
}
