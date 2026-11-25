<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    public $incrementing = false;

    protected $fillable = ['hero', 'how_it_works', 'settings', 'featured_content_ids'];

    protected function casts(): array
    {
        return [
            'hero' => 'array',
            'how_it_works' => 'array',
            'settings' => 'array',
            'featured_content_ids' => 'array',
        ];
    }
}
