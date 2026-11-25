<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityParticipation;
use App\Models\Category;
use App\Models\Content;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Quiz;
use App\Models\QuizAttempt;
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

    private const PER_PAGE = 50;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $category = Category::factory()->create(['name' => 'Unica']);
        $zone = Zone::factory()->create(['name' => 'Centro']);
        $author = User::factory()->create(['name' => 'Autor']);
        $this->admin = User::factory()->admin()->create();

        Category::factory()->count(49)->sequence(fn ($sequence) => ['name' => 'Tema '.$sequence->index])->create();
        Zone::factory()->count(49)->sequence(fn ($sequence) => ['name' => 'Zona '.$sequence->index])->create();

        Content::factory()->count(self::PER_PAGE)->published()->for($category)->create(['author_id' => $author->id]);
        $activities = Activity::factory()->count(self::PER_PAGE)->open()->for($category)->create(['author_id' => $author->id]);
        $events = Event::factory()->count(self::PER_PAGE)->published()->create([
            'author_id' => $author->id,
            'category_id' => $category->id,
            'zone_id' => $zone->id,
        ]);
        $quizzes = Quiz::factory()->count(self::PER_PAGE)->published()->withQuestions()->create([
            'category_id' => $category->id,
            'author_id' => $author->id,
        ]);

        Report::factory()->count(self::PER_PAGE)->resolved()->create([
            'category_id' => $category->id,
            'zone_id' => $zone->id,
        ]);
        Report::factory()->count(self::PER_PAGE)->resolved()->create([
            'user_id' => $this->admin->id,
            'category_id' => $category->id,
            'zone_id' => $zone->id,
        ]);

        ActivityParticipation::factory()->count(self::PER_PAGE)->create([
            'activity_id' => $activities->first()->id,
        ]);

        QuizAttempt::factory()->count(self::PER_PAGE)->for($quizzes->first())->for($this->admin)->create();

        EventRegistration::factory()->count(self::PER_PAGE)->create([
            'event_id' => $events->first()->id,
        ]);
        foreach ($events as $event) {
            EventRegistration::factory()->for($event)->for($this->admin)->create();
        }
    }

    public function test_public_listings_do_not_n_plus_one(): void
    {
        foreach ([
            '/api/v1/categories',
            '/api/v1/zones',
            '/api/v1/contents',
            '/api/v1/activities',
            '/api/v1/events',
            '/api/v1/reports/public',
        ] as $url) {
            $this->assertListingQueryCount($url, 6);
        }
    }

    public function test_authenticated_listings_do_not_n_plus_one(): void
    {
        $this->actingAs($this->admin, 'sanctum');

        foreach ([
            '/api/v1/quizzes',
            '/api/v1/reports',
            '/api/v1/users',
            '/api/v1/activity-participations',
            '/api/v1/me/participations',
            '/api/v1/me/reports',
            '/api/v1/me/attempts',
            '/api/v1/me/registrations',
            '/api/v1/events/'.Event::firstOrFail()->getKey().'/registrations',
        ] as $url) {
            $this->assertListingQueryCount($url, 8);
        }
    }

    private function assertListingQueryCount(string $url, int $max): void
    {
        $url .= (str_contains($url, '?') ? '&' : '?').'per_page='.self::PER_PAGE;

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->getJson($url)->assertOk();

        $queries = count(DB::getQueryLog());

        DB::disableQueryLog();

        $this->assertLessThanOrEqual($max, $queries, "{$url} executou {$queries} queries.");
    }
}
