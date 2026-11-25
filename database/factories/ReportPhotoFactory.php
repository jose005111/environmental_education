<?php

namespace Database\Factories;

use App\Models\Report;
use App\Models\ReportPhoto;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ReportPhoto>
 */
class ReportPhotoFactory extends Factory
{
    protected $model = ReportPhoto::class;

    public function definition(): array
    {
        return [
            'report_id' => Report::factory(),
            'path' => 'reports/'.Str::random(24).'.jpg',
        ];
    }
}
