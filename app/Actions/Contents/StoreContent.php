<?php

namespace App\Actions\Contents;

use App\Enums\ContentStatus;
use App\Models\Content;
use App\Services\StoredFile;
use Illuminate\Http\UploadedFile;

final readonly class StoreContent
{
    public function __construct(private StoredFile $files) {}

    public function execute(array $data, int $authorId): Content
    {
        $cover = $data['cover_image'] ?? null;
        unset($data['cover_image']);

        if ($cover instanceof UploadedFile) {
            $data['cover_image'] = $this->files->store($cover, 'covers')['path'];
        }

        $data['author_id'] = $authorId;
        $data['status'] = ContentStatus::RASCUNHO;

        return Content::create($data);
    }
}
