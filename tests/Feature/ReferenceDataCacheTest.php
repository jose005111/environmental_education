<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferenceDataCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_index_is_cached_and_invalidated_by_model_events(): void
    {
        $category = Category::factory()->create([
            'name' => 'Tema Antigo',
            'slug' => 'tema-em-cache',
        ]);

        $url = '/api/v1/categories?filter[slug]=tema-em-cache';

        $this->getJson($url)
            ->assertOk()
            ->assertJsonFragment(['name' => 'Tema Antigo']);

        Category::query()->whereKey($category->getKey())->update(['name' => 'Tema Intermediario']);

        $this->getJson($url)
            ->assertOk()
            ->assertJsonFragment(['name' => 'Tema Antigo']);

        $category->refresh()->update(['name' => 'Tema Novo']);

        $this->getJson($url)
            ->assertOk()
            ->assertJsonFragment(['name' => 'Tema Novo']);
    }

    public function test_zone_index_is_cached_and_invalidated_by_model_events(): void
    {
        $zone = Zone::factory()->create(['name' => 'Zona Cache Antiga']);

        $url = '/api/v1/zones?filter[name]=Zona Cache';

        $this->getJson($url)
            ->assertOk()
            ->assertJsonFragment(['name' => 'Zona Cache Antiga']);

        Zone::query()->whereKey($zone->getKey())->update(['name' => 'Zona Cache Intermediaria']);

        $this->getJson($url)
            ->assertOk()
            ->assertJsonFragment(['name' => 'Zona Cache Antiga']);

        $zone->refresh()->update(['name' => 'Zona Cache Nova']);

        $this->getJson($url)
            ->assertOk()
            ->assertJsonFragment(['name' => 'Zona Cache Nova']);
    }
}
