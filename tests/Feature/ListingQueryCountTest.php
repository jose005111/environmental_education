<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Activity;
use App\Models\ActivityParticipation;
use App\Models\Category;
use App\Models\Content;
use App\Models\Event;
use App\Models\Quiz;
use App\Models\Report;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ListingQueryCountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $category = Category::factory()->create(['name' => 'Unica']);
        $zone = Zone::factory()->create(['name' => 'Centro']);
        $author = User::factory()->create(['name' => 'Autor']);

        Content::factory()->count(20)->published()->for($category)->create(['author_id' => $author->id]);
        Activity::factory()->count(20)->open()->for($category)->create(['author_id' => $author->id]);
        Event::factory()->count(20)->published()->create([
            'author_id' => $author->id,
            'category_id' => $category->id,
            'zone_id' => $zone->id,
        ]);
        Report::factory()->count(20)->resolved()->create([
            'category_id' => $category->id,
            'zone_id' => $zone->id,
        ]);
        Quiz::factory()->count(20)->published()->withQuestions()->create([
            'category_id' => $category->id,
            'author_id' => $author->id,
        ]);
        ActivityParticipation::factory()->count(20)->create([
            'activity_id' => Activity::firstOrFail()->id,
        ]);
    }

    public function test_public_listings_do_not_n_plus_one(): void
    {
        foreach (['/api/v1/contents', '/api/v1/activities', '/api/v1/events', '/api/v1/reports/public'] as $url) {
            $this->assertListingQueryCount($url, 15);
        }
    }

    public function test_authenticated_listings_do_not_n_plus_one(): void
    {
        $admin = $this->actingAsRole(RoleName::ADMINISTRADOR);

        foreach (['/api/v1/quizzes', '/api/v1/reports', '/api/v1/activity-participations', '/api/v1/users'] as $url) {
            $this->assertListingQueryCount($url, 20, $admin);
        }
    }

    private function assertListingQueryCount(string $url, int $max, ?User $user = null): void
    {
        if ($user !== null) {
            $this->actingAs($user, 'sanctum');
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->getJson($url)->assertOk();

        $queries = count(DB::getQueryLog());

        DB::disableQueryLog();

        $this->assertLessThanOrEqual($max, $queries, "{$url} executou {$queries} queries.");
    }
}
