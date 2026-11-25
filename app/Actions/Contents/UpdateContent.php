<?php

namespace App\Actions\Contents;

use App\Models\Content;
use App\Services\StoredFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final readonly class UpdateContent
{
    public function __construct(private StoredFile $files) {}

    public function execute(Content $content, array $data): Content
    {
        $cover = $data['cover_image'] ?? null;
        unset($data['cover_image']);

        $previousCover = $content->cover_image;
        $storedCover = null;

        DB::transaction(function () use ($content, $data, $cover, &$storedCover): void {
            if ($cover instanceof UploadedFile) {
                $storedCover = $this->files->store($cover, 'covers');
                $data['cover_image'] = $storedCover['path'];
            }

            $content->fill($data)->save();
        });

        if ($storedCover !== null && $previousCover !== null && $previousCover !== $storedCover['path']) {
            $this->files->delete($previousCover);
        }

        return $content->refresh();
    }
}
