<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Activity;
use App\Models\Content;
use App\Models\Event;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModeratorDraftVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_content_drafts_are_hidden_from_public_and_residents_but_visible_to_staff(): void
    {
        $draft = Content::factory()->create();

        $this->getJson('/api/v1/contents')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/contents?filter[status]=rascunho')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/contents/{$draft->slug}")->assertNotFound();

        $this->actingAsRole(RoleName::RESIDENTE);
        $this->getJson('/api/v1/contents')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/contents/{$draft->slug}")->assertNotFound();

        $this->actingAsRole(RoleName::MODERADOR);
        $this->getJson('/api/v1/contents?filter[status]=rascunho')->assertOk()
            ->assertJsonPath('data.0.id', $draft->id);
        $this->getJson("/api/v1/contents/{$draft->slug}")->assertOk()
            ->assertJsonPath('data.status', 'rascunho');
        $this->assertSame(0, $draft->refresh()->views_count);

        $this->actingAsRole(RoleName::ADMINISTRADOR);
        $this->getJson('/api/v1/contents')->assertOk()
            ->assertJsonPath('data.0.id', $draft->id);
    }

    public function test_activity_drafts_are_hidden_from_public_and_residents_but_visible_to_staff(): void
    {
        $draft = Activity::factory()->create();

        $this->getJson('/api/v1/activities')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/activities?filter[status]=rascunho')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/activities/{$draft->slug}")->assertNotFound();

        $this->actingAsRole(RoleName::RESIDENTE);
        $this->getJson('/api/v1/activities')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/activities/{$draft->slug}")->assertNotFound();

        $this->actingAsRole(RoleName::MODERADOR);
        $this->getJson('/api/v1/activities?filter[status]=rascunho')->assertOk()
            ->assertJsonPath('data.0.id', $draft->id);
        $this->getJson("/api/v1/activities/{$draft->slug}")->assertOk()
            ->assertJsonPath('data.status', 'rascunho');

        $this->actingAsRole(RoleName::ADMINISTRADOR);
        $this->getJson('/api/v1/activities')->assertOk()
            ->assertJsonPath('data.0.id', $draft->id);
    }

    public function test_event_drafts_are_hidden_from_public_and_residents_but_visible_to_staff(): void
    {
        $draft = Event::factory()->create();

        $this->getJson('/api/v1/events')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/events?filter[status]=rascunho')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/events/{$draft->slug}")->assertNotFound();

        $this->actingAsRole(RoleName::RESIDENTE);
        $this->getJson('/api/v1/events')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/events/{$draft->slug}")->assertNotFound();

        $this->actingAsRole(RoleName::MODERADOR);
        $this->getJson('/api/v1/events?filter[status]=rascunho')->assertOk()
            ->assertJsonPath('data.0.id', $draft->id);
        $this->getJson("/api/v1/events/{$draft->slug}")->assertOk()
            ->assertJsonPath('data.status', 'rascunho');

        $this->actingAsRole(RoleName::ADMINISTRADOR);
        $this->getJson('/api/v1/events')->assertOk()
            ->assertJsonPath('data.0.id', $draft->id);
    }
}
