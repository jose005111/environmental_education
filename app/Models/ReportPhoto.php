<?php

namespace App\Models;

use App\Models\Concerns\DeletesStoredFiles;
use Database\Factories\ReportPhotoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportPhoto extends Model
{
    /** @use HasFactory<ReportPhotoFactory> */
    use DeletesStoredFiles, HasFactory;

    protected $fillable = ['report_id', 'path'];

    protected function storedFileColumns(): array
    {
        return ['path'];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }
}
