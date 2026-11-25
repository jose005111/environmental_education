<?php

namespace Tests\Unit;

use App\Jobs\ProcessUploadedImage;
use App\Services\StoredFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProcessUploadedImageTest extends TestCase
{
    public function test_it_creates_a_webp_thumbnail_for_stored_images(): void
    {
        if (! function_exists('imagewebp')) {
            $this->markTestSkipped('WebP support is unavailable.');
        }

        Storage::fake('public');

        $stored = app(StoredFile::class)->store(
            UploadedFile::fake()->image('foto.jpg', 1600, 1200),
            'reports',
        );

        $thumbnail = Str::beforeLast($stored['path'], '.').'-thumb.webp';

        Storage::disk('public')->assertExists($stored['path']);
        Storage::disk('public')->assertExists($thumbnail);

        $size = getimagesizefromstring(Storage::disk('public')->get($thumbnail));

        $this->assertIsArray($size);
        $this->assertLessThanOrEqual(1200, max($size[0], $size[1]));
    }

    public function test_it_ignores_missing_files(): void
    {
        Storage::fake('public');

        ProcessUploadedImage::dispatch('reports/inexistente.jpg', 'public');

        Storage::disk('public')->assertMissing('reports/inexistente-thumb.webp');
    }
}
