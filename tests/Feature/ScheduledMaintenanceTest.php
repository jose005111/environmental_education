<?php

namespace Tests\Feature;

use App\Enums\ActivityStatus;
use App\Enums\EventStatus;
use App\Models\Activity;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduledMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_concludes_only_expired_published_events(): void
    {
        Event::factory()->published()->create(['ends_at' => now()->subMinute()]);
        Event::factory()->published()->create(['ends_at' => now()->addDay()]);
        Event::factory()->create(['ends_at' => now()->subMinute(), 'status' => EventStatus::RASCUNHO]);

        $this->artisan('events:conclude-expired')->assertSuccessful();

        $this->assertSame(1, Event::query()->where('status', EventStatus::CONCLUIDO)->count());
    }

    public function test_it_closes_only_expired_open_activities(): void
    {
        Activity::factory()->open()->create(['ends_on' => now()->subDay()->toDateString()]);
        Activity::factory()->open()->create(['ends_on' => now()->addDay()->toDateString()]);
        Activity::factory()->create([
            'ends_on' => now()->subDay()->toDateString(),
            'status' => ActivityStatus::RASCUNHO,
        ]);

        $this->artisan('activities:close-expired')->assertSuccessful();

        $this->assertSame(1, Activity::query()->where('status', ActivityStatus::ENCERRADA)->count());
    }
}
