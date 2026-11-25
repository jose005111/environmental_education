<?php

namespace App\Actions\Contents;

use App\Enums\ContentStatus;
use App\Models\Content;
use Illuminate\Support\Facades\DB;

final readonly class PublishContent
{
    public function execute(Content $content, ContentStatus|string $status): Content
    {
        $status = $status instanceof ContentStatus ? $status : ContentStatus::from($status);

        return DB::transaction(function () use ($content, $status): Content {
            if ($status === ContentStatus::PUBLICADO) {
                $content->published_at = now();
            }

            $content->status = $status;
            $content->save();

            return $content->refresh();
        });
    }
}
