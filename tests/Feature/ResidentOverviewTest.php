<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\ActivityParticipation;
use App\Models\EventRegistration;
use App\Models\QuizAttempt;
use App\Models\Report;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResidentOverviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_only_staff_can_search_and_open_resident_records(): void
    {
        $resident = User::factory()->create(['name' => 'Ana Moradora']);
        $moderator = User::factory()->moderator()->create(['name' => 'Ana Moderadora']);

        $this->getJson('/api/v1/residents?search=Ana')->assertUnauthorized();
        $this->actingAsRole(RoleName::RESIDENTE);
        $this->getJson('/api/v1/residents?search=Ana')->assertForbidden();
        $this->getJson("/api/v1/residents/{$resident->id}")->assertForbidden();

        $this->actingAs($moderator);
        $this->getJson('/api/v1/residents?search=Ana')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $resident->id);
        $this->getJson("/api/v1/residents/{$moderator->id}")->assertNotFound();
        $this->getJson('/api/v1/residents?search=A')->assertUnprocessable();

        $this->actingAsRole(RoleName::ADMINISTRADOR);
        $this->getJson("/api/v1/residents/{$resident->id}")->assertOk()
            ->assertJsonPath('data.resident.name', 'Ana Moradora');
    }

    public function test_overview_counts_and_paged_records_belong_only_to_selected_resident(): void
    {
        $resident = User::factory()->create();
        $other = User::factory()->create();
        $attempt = QuizAttempt::factory()->create(['user_id' => $resident->id]);
        ActivityParticipation::factory()->create(['user_id' => $resident->id]);
        EventRegistration::factory()->present()->create(['user_id' => $resident->id]);
        Report::factory()->create(['user_id' => $resident->id]);
        QuizAttempt::factory()->create(['user_id' => $other->id]);

        $this->actingAsRole(RoleName::MODERADOR);
        $this->getJson("/api/v1/residents/{$resident->id}")->assertOk()
            ->assertJsonPath('data.counts.quizzes', 1)
            ->assertJsonPath('data.counts.activities', 1)
            ->assertJsonPath('data.counts.events', 1)
            ->assertJsonPath('data.counts.reports', 1);

        foreach (['quizzes', 'activities', 'events', 'reports'] as $type) {
            $this->getJson("/api/v1/residents/{$resident->id}/records?type={$type}")
                ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonCount(1, 'data');
        }

        $this->getJson("/api/v1/residents/{$resident->id}/records?type=quizzes")
            ->assertJsonPath('data.0.id', $attempt->id);
        $this->getJson("/api/v1/residents/{$resident->id}/records?type=invalid")
            ->assertUnprocessable();
    }
}
