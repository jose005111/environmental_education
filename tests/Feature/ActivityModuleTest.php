<?php

namespace Tests\Feature;

use App\Enums\ParticipationStatus;
use App\Models\Activity;
use App\Models\ActivityParticipation;
use App\Models\Category;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ActivityModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Storage::fake('public');
    }

    public function test_public_index_lists_only_open_and_closed_activities(): void
    {
        $open = Activity::factory()->open()->create(['title' => 'Limpeza da praia']);
        $closed = Activity::factory()->closed()->create(['title' => 'Reciclagem na escola']);
        Activity::factory()->create(['title' => 'Actividade secreta']);

        $response = $this->getJson('/api/v1/activities')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'title', 'slug', 'summary', 'status', 'status_label', 'starts_on', 'ends_on']],
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'from', 'last_page', 'per_page', 'to', 'total'],
            ]);

        $ids = array_column($response->json('data'), 'id');

        $this->assertContains($open->id, $ids);
        $this->assertContains($closed->id, $ids);
        $this->assertArrayNotHasKey('instructions', $response->json('data.0'));
    }

    public function test_public_show_returns_full_resource_and_hides_drafts(): void
    {
        $activity = Activity::factory()->open()->create(['title' => 'Oficina de compostagem']);

        $this->getJson("/api/v1/activities/{$activity->slug}")
            ->assertOk()
            ->assertJsonPath('data.id', $activity->id)
            ->assertJsonPath('data.slug', $activity->slug)
            ->assertJsonPath('data.status', 'aberta')
            ->assertJsonStructure(['data' => ['id', 'title', 'instructions', 'author', 'category', 'participations_count']]);

        $draft = Activity::factory()->create();

        $this->getJson("/api/v1/activities/{$draft->slug}")->assertNotFound();
        $this->getJson('/api/v1/activities/slug-inexistente')->assertNotFound();

        $this->actingAsRole('moderador');

        $this->getJson("/api/v1/activities/{$draft->slug}")
            ->assertOk()
            ->assertJsonPath('data.status', 'rascunho');
    }

    public function test_moderator_creates_activity_and_invalid_payloads_are_rejected(): void
    {
        $category = Category::factory()->create();
        $payload = [
            'category_id' => $category->id,
            'title' => 'Mutirão de recolha',
            'summary' => 'Recolha de resíduos no bairro.',
            'instructions' => 'Trazer luvas e sacos.',
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addDays(3)->toDateString(),
            'status' => 'aberta',
        ];

        $this->postJson('/api/v1/activities', $payload)->assertUnauthorized();

        $this->actingAsRole('residente');

        $this->postJson('/api/v1/activities', $payload)
            ->assertForbidden();

        $moderator = $this->actingAsRole('moderador');

        $this->postJson('/api/v1/activities', $payload)
            ->assertCreated()
            ->assertJsonPath('data.title', 'Mutirão de recolha')
            ->assertJsonPath('data.status', 'aberta')
            ->assertJsonPath('data.author_id', $moderator->id);

        $this->assertDatabaseHas('activities', [
            'title' => 'Mutirão de recolha',
            'author_id' => $moderator->id,
            'status' => 'aberta',
        ]);

        $this->postJson('/api/v1/activities', [
            ...$payload,
            'status' => 'invalido',
            'starts_on' => now()->addDays(5)->toDateString(),
            'ends_on' => now()->toDateString(),
        ])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['status', 'ends_on']]);
    }

    public function test_moderator_updates_and_deletes_activity(): void
    {
        $activity = Activity::factory()->open()->create();

        $this->putJson("/api/v1/activities/{$activity->id}", ['title' => 'Sem acesso'])
            ->assertUnauthorized();

        $this->actingAsRole('residente');

        $this->putJson("/api/v1/activities/{$activity->id}", ['title' => 'Sem permissão'])
            ->assertForbidden();

        $this->actingAsRole('moderador');

        $this->putJson("/api/v1/activities/{$activity->id}", [
            'title' => 'Oficina de reciclagem',
            'status' => 'encerrada',
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Oficina de reciclagem')
            ->assertJsonPath('data.status', 'encerrada');

        $this->deleteJson("/api/v1/activities/{$activity->id}")->assertNoContent();

        $this->assertSoftDeleted('activities', ['id' => $activity->id]);

        $this->deleteJson('/api/v1/activities/999999')->assertNotFound();
    }

    public function test_activity_photos_can_be_added_and_removed_on_edit_with_category_preserved(): void
    {
        $category = Category::factory()->create();
        $this->actingAsRole('moderador');

        $created = $this->post('/api/v1/activities', [
            'category_id' => $category->id,
            'title' => 'Oficina com fotografias',
            'summary' => 'Registo da oficina.',
            'instructions' => 'Trazer material.',
            'status' => 'aberta',
            'photos' => [UploadedFile::fake()->image('primeira.jpg')],
        ])->assertCreated()->assertJsonPath('data.category.name', $category->name);

        $id = $created->json('data.id');
        $photoId = $created->json('data.photos.0.id');
        $path = Activity::findOrFail($id)->photos()->firstOrFail()->path;
        Storage::disk('public')->assertExists($path);
        $this->getJson("/api/v1/activities/{$created->json('data.slug')}")
            ->assertJsonPath('data.photos.0.id', $photoId);

        $this->post("/api/v1/activities/{$id}", [
            '_method' => 'PUT',
            'category_id' => $category->id,
            'removed_photo_ids' => [$photoId],
            'photos' => [UploadedFile::fake()->image('segunda.png')],
        ])->assertOk()->assertJsonCount(1, 'data.photos');

        Storage::disk('public')->assertMissing($path);
        $this->assertDatabaseMissing('activity_photos', ['id' => $photoId]);
        $this->assertDatabaseCount('activity_photos', 1);
    }

    public function test_activity_rejects_more_than_five_photos(): void
    {
        $this->actingAsRole('moderador');
        $activity = Activity::factory()->create();

        $this->post("/api/v1/activities/{$activity->id}", [
            '_method' => 'PUT',
            'photos' => array_map(fn ($number) => UploadedFile::fake()->image("{$number}.jpg"), range(1, 6)),
        ])->assertUnprocessable()->assertJsonValidationErrors('photos');
    }

    public function test_activity_cannot_remove_another_activity_photo_or_exceed_total_limit(): void
    {
        $this->actingAsRole('moderador');
        $activity = Activity::factory()->create();
        $other = Activity::factory()->create();
        $otherPhoto = $other->photos()->create(['path' => 'activities/other.jpg']);
        foreach (range(1, 5) as $number) {
            $activity->photos()->create(['path' => "activities/{$number}.jpg"]);
        }

        $this->putJson("/api/v1/activities/{$activity->id}", [
            'removed_photo_ids' => [$otherPhoto->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('removed_photo_ids.0');

        $this->post("/api/v1/activities/{$activity->id}", [
            '_method' => 'PUT',
            'photos' => [UploadedFile::fake()->image('extra.jpg')],
        ])->assertUnprocessable()->assertJsonValidationErrors('photos');

        $this->assertDatabaseCount('activity_photos', 6);
    }

    public function test_activity_with_participations_cannot_be_deleted(): void
    {
        $activity = Activity::factory()->open()->create();

        ActivityParticipation::factory()->for($activity)->create();

        $this->actingAsRole('moderador');

        $this->deleteJson("/api/v1/activities/{$activity->id}")
            ->assertConflict()
            ->assertJsonPath('code', 'conflict');
    }

    public function test_join_creates_inscription_and_rejects_duplicates_and_outside_window(): void
    {
        $resident = $this->actingAsRole('residente');
        $activity = Activity::factory()->open()->create();

        $this->postJson("/api/v1/activities/{$activity->id}/join")
            ->assertCreated()
            ->assertJsonPath('data.status', 'inscrito')
            ->assertJsonPath('data.user_id', $resident->id);

        $this->assertDatabaseHas('activity_participations', [
            'activity_id' => $activity->id,
            'user_id' => $resident->id,
            'status' => 'inscrito',
        ]);

        $this->postJson("/api/v1/activities/{$activity->id}/join")
            ->assertConflict()
            ->assertJsonPath('code', 'conflict');

        $future = Activity::factory()->open()->create([
            'starts_on' => now()->addDay()->toDateString(),
            'ends_on' => now()->addDays(5)->toDateString(),
        ]);

        $this->postJson("/api/v1/activities/{$future->id}/join")
            ->assertConflict()
            ->assertJsonPath('status', 409);

        $expired = Activity::factory()->open()->create([
            'starts_on' => now()->subDays(10)->toDateString(),
            'ends_on' => now()->subDay()->toDateString(),
        ]);

        $this->postJson("/api/v1/activities/{$expired->id}/join")->assertConflict();

        $draft = Activity::factory()->create();

        $this->postJson("/api/v1/activities/{$draft->id}/join")->assertForbidden();
    }

    public function test_join_requires_authentication_and_resident_role(): void
    {
        $activity = Activity::factory()->open()->create();

        $this->postJson("/api/v1/activities/{$activity->id}/join")->assertUnauthorized();

        $this->actingAsRole('moderador');

        $this->postJson("/api/v1/activities/{$activity->id}/join")
            ->assertForbidden();
    }

    public function test_participant_submits_proof_from_inscrito_and_only_own_participation(): void
    {
        $resident = $this->actingAsRole('residente');
        $other = User::factory()->create();
        $activity = Activity::factory()->open()->create();

        $participation = ActivityParticipation::factory()->for($activity)->for($resident)->create();
        ActivityParticipation::factory()->for($activity)->for($other)->create();

        $this->postJson("/api/v1/activities/{$activity->id}/submit", [
            'notes' => 'Recolhi papel e plástico.',
            'proof_photo' => UploadedFile::fake()->image('prova.png', 100, 100),
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'submetido')
            ->assertJsonPath('data.notes', 'Recolhi papel e plástico.')
            ->assertJsonPath('data.reviewer', null);

        $participation->refresh();

        $this->assertSame(ParticipationStatus::SUBMETIDO, $participation->status);
        $this->assertNotNull($participation->submitted_at);
        Storage::disk('public')->assertExists($participation->proof_photo);

        $this->postJson("/api/v1/activities/{$activity->id}/submit", [
            'notes' => 'Outra tentativa.',
            'proof_photo' => UploadedFile::fake()->image('outra.png', 100, 100),
        ])->assertForbidden();

        $activityWithoutParticipation = Activity::factory()->open()->create();
        ActivityParticipation::factory()->for($activityWithoutParticipation)->for($resident)->create();

        $this->actingAs($other, 'sanctum')
            ->postJson("/api/v1/activities/{$activityWithoutParticipation->id}/submit", [
                'proof_photo' => UploadedFile::fake()->image('alheia.png', 100, 100),
            ])
            ->assertNotFound();
    }

    public function test_rejected_participation_can_resubmit_and_replace_proof(): void
    {
        $resident = $this->actingAsRole('residente');
        $activity = Activity::factory()->open()->create();

        Storage::disk('public')->put('activities/prova-antiga.jpg', 'conteudo antigo');

        $participation = ActivityParticipation::factory()
            ->for($activity)
            ->for($resident)
            ->rejected()
            ->create(['proof_photo' => 'activities/prova-antiga.jpg']);

        $this->postJson("/api/v1/activities/{$activity->id}/submit", [
            'notes' => 'Prova corrigida.',
            'proof_photo' => UploadedFile::fake()->image('nova.jpg', 100, 100),
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'submetido');

        $participation->refresh();

        $this->assertSame(ParticipationStatus::SUBMETIDO, $participation->status);
        $this->assertSame('Prova corrigida.', $participation->notes);
        $this->assertNotNull($participation->submitted_at);
        $this->assertNull($participation->reviewed_by);
        $this->assertNull($participation->reviewed_at);
        $this->assertNull($participation->review_note);
        $this->assertNotSame('activities/prova-antiga.jpg', $participation->proof_photo);

        Storage::disk('public')->assertMissing('activities/prova-antiga.jpg');
        Storage::disk('public')->assertExists($participation->proof_photo);
    }

    public function test_submit_rejects_invalid_mime_type_and_oversized_file(): void
    {
        $resident = $this->actingAsRole('residente');
        $activity = Activity::factory()->open()->create();

        ActivityParticipation::factory()->for($activity)->for($resident)->create();

        $this->postJson("/api/v1/activities/{$activity->id}/submit", [
            'proof_photo' => UploadedFile::fake()->create('prova.txt', 10, 'text/plain'),
        ])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['proof_photo']]);

        $this->postJson("/api/v1/activities/{$activity->id}/submit", [
            'proof_photo' => UploadedFile::fake()->image('grande.jpg')->size(6000),
        ])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['proof_photo']]);
    }

    public function test_moderator_reviews_submitted_proofs_with_validated_or_rejected_outcome(): void
    {
        $moderator = $this->actingAsRole('moderador');

        $approved = ActivityParticipation::factory()
            ->for(User::factory()->create())
            ->submitted()
            ->create();

        $this->patchJson("/api/v1/activity-participations/{$approved->id}/review", [
            'status' => 'validado',
            'review_note' => 'Prova conferida.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'validado')
            ->assertJsonPath('data.reviewed_by', $moderator->id)
            ->assertJsonPath('data.review_note', 'Prova conferida.');

        $approved->refresh();

        $this->assertSame(ParticipationStatus::VALIDADO, $approved->status);
        $this->assertSame($moderator->id, $approved->reviewed_by);
        $this->assertNotNull($approved->reviewed_at);

        $rejected = ActivityParticipation::factory()
            ->for(User::factory()->create())
            ->submitted()
            ->create();

        $this->patchJson("/api/v1/activity-participations/{$rejected->id}/review", [
            'status' => 'rejeitado',
            'review_note' => 'Foto ilegível.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejeitado');

        $this->assertSame(ParticipationStatus::REJEITADO, $rejected->fresh()->status);
    }

    public function test_review_requires_authentication_moderator_submitted_state_and_not_own_proof(): void
    {
        $participation = ActivityParticipation::factory()
            ->for(User::factory()->create())
            ->submitted()
            ->create();

        $this->patchJson("/api/v1/activity-participations/{$participation->id}/review", ['status' => 'validado'])
            ->assertUnauthorized();

        $this->actingAsRole('residente');

        $this->patchJson("/api/v1/activity-participations/{$participation->id}/review", ['status' => 'validado'])
            ->assertForbidden();

        $moderator = $this->actingAsRole('moderador');

        $own = ActivityParticipation::factory()->for($moderator)->submitted()->create();

        $this->patchJson("/api/v1/activity-participations/{$own->id}/review", ['status' => 'validado'])
            ->assertForbidden();

        $alreadyReviewed = ActivityParticipation::factory()
            ->for(User::factory()->create())
            ->validated()
            ->create();

        $this->patchJson("/api/v1/activity-participations/{$alreadyReviewed->id}/review", ['status' => 'rejeitado'])
            ->assertForbidden();

        $this->patchJson('/api/v1/activity-participations/999999/review', ['status' => 'validado'])
            ->assertNotFound();
    }

    public function test_moderator_queue_filters_by_status_and_paginates(): void
    {
        $submitted = ActivityParticipation::factory()->submitted()->create();
        $validated = ActivityParticipation::factory()->validated()->create();
        $enrolled = ActivityParticipation::factory()->create();

        $this->getJson('/api/v1/activity-participations')->assertUnauthorized();

        $this->actingAsRole('residente');

        $this->getJson('/api/v1/activity-participations')
            ->assertForbidden();

        $this->actingAsRole('moderador');

        $this->getJson('/api/v1/activity-participations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $submitted->id)
            ->assertJsonStructure([
                'data' => [['id', 'status', 'activity', 'user', 'submitted_at']],
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'per_page', 'total'],
            ]);

        $this->getJson('/api/v1/activity-participations?filter[status]=validado')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $validated->id);

        $this->getJson('/api/v1/activity-participations?filter[status]=inscrito')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $enrolled->id);

        $this->getJson('/api/v1/activity-participations?filter[status]=submetido,validado')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson("/api/v1/activity-participations?filter[activity]={$submitted->activity_id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_resident_sees_only_own_participation_history(): void
    {
        $resident = $this->actingAsRole('residente');
        $other = User::factory()->create();

        ActivityParticipation::factory()->count(2)->for($resident)->create();
        ActivityParticipation::factory()->submitted()->for($other)->create();

        $this->getJson('/api/v1/me/participations')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'activity', 'status', 'user']],
                'meta' => ['total'],
            ]);

        $this->getJson('/api/v1/me/participations?filter[status]=submetido')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/v1/me/participations')->assertOk();

        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/me/participations')->assertUnauthorized();
    }

    public function test_deleting_participation_removes_its_proof_file(): void
    {
        Storage::disk('public')->put('activities/prova.jpg', 'conteudo');

        $participation = ActivityParticipation::factory()->create([
            'proof_photo' => 'activities/prova.jpg',
        ]);

        Storage::disk('public')->assertExists('activities/prova.jpg');

        $participation->delete();

        Storage::disk('public')->assertMissing('activities/prova.jpg');
    }
}
