<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Activity;
use App\Models\ActivityParticipation;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationDetailStateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_activity_detail_reports_current_users_participation(): void
    {
        $activity = Activity::factory()->open()->create();
        $resident = User::factory()->create();
        ActivityParticipation::factory()->create(['activity_id' => $activity->id, 'user_id' => $resident->id]);

        $this->getJson("/api/v1/activities/{$activity->slug}")->assertOk()
            ->assertJsonPath('data.my_participation_status', null);

        $this->actingAs($resident)->getJson("/api/v1/activities/{$activity->slug}")->assertOk()
            ->assertJsonPath('data.my_participation_status', 'inscrito');

        $this->actingAsRole(RoleName::RESIDENTE);
        $this->getJson("/api/v1/activities/{$activity->slug}")->assertOk()
            ->assertJsonPath('data.my_participation_status', null);
    }

    public function test_event_detail_reports_active_and_cancelled_registration(): void
    {
        $event = Event::factory()->published()->create();
        $resident = User::factory()->create();
        $registration = EventRegistration::factory()->create(['event_id' => $event->id, 'user_id' => $resident->id]);

        $this->actingAs($resident)->getJson("/api/v1/events/{$event->slug}")->assertOk()
            ->assertJsonPath('data.my_registration_status', 'inscrito');

        $registration->update(['status' => 'cancelado']);
        $this->getJson("/api/v1/events/{$event->slug}")->assertOk()
            ->assertJsonPath('data.my_registration_status', 'cancelado');
    }
}
