<?php

namespace App\Actions\Reports;

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;
use App\Services\StoredFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CreateReport
{
    public function __construct(private StoredFile $files) {}

    public function execute(array $data, User $author, array $photos = []): Report
    {
        if ($photos === [] && isset($data['photos']) && is_array($data['photos'])) {
            $photos = $data['photos'];
        }

        $photos = array_values(array_filter(
            $photos,
            fn (mixed $photo): bool => $photo instanceof UploadedFile,
        ));

        $limit = (int) config('uploads.report_photos_limit', 5);

        if (count($photos) > $limit) {
            throw ValidationException::withMessages([
                'photos' => "O máximo de {$limit} fotografias por reporte foi excedido.",
            ]);
        }

        unset(
            $data['photos'],
            $data['user_id'],
            $data['status'],
            $data['assigned_to'],
            $data['is_public'],
            $data['resolved_at'],
        );

        return DB::transaction(function () use ($data, $author, $photos): Report {
            $report = Report::create([
                'user_id' => $author->getKey(),
                'zone_id' => $data['zone_id'],
                'category_id' => $data['category_id'] ?? null,
                'title' => $data['title'],
                'description' => $data['description'],
                'location_note' => $data['location_note'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'status' => ReportStatus::RECEBIDO,
                'is_public' => false,
                'resolved_at' => null,
            ]);

            foreach ($photos as $photo) {
                $report->photos()->create([
                    'path' => $this->files->store($photo, 'reports')['path'],
                ]);
            }

            $report->updates()->create([
                'user_id' => $author->getKey(),
                'from_status' => null,
                'to_status' => ReportStatus::RECEBIDO,
            ]);

            return $report;
        });
    }

    public function __invoke(array $data, User $author, array $photos = []): Report
    {
        return $this->execute($data, $author, $photos);
    }
}
