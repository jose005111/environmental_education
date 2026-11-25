<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\RegistrationStatus;
use App\Enums\RoleName;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EventModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_list_and_detail_only_show_visible_events(): void
    {
        $published = Event::factory()->published()->create(['title' => 'Feira de reciclagem']);
        Event::factory()->cancelled()->create();
        Event::factory()->concluded()->create();
        $draft = Event::factory()->create();

        $response = $this->getJson('/api/v1/events')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'title', 'slug', 'status', 'vagas_livres']],
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'from', 'last_page', 'per_page', 'to', 'total'],
            ]);

        $this->assertNotContains($draft->slug, array_column($response->json('data'), 'slug'));

        $this->getJson("/api/v1/events/{$published->slug}")
            ->assertOk()
            ->assertJsonPath('data.slug', $published->slug)
            ->assertJsonPath('data.status', EventStatus::PUBLICADO->value);

        $this->getJson("/api/v1/events/{$draft->slug}")->assertNotFound();
        $this->getJson('/api/v1/events/slug-inexistente')->assertNotFound();

        $this->actingAsRole(RoleName::MODERADOR);
        $this->getJson("/api/v1/events/{$draft->slug}")->assertNotFound();
    }

    public function test_public_event_detail_does_not_expose_author_contact(): void
    {
        $author = User::factory()->create([
            'name' => 'Autor do Evento',
            'email' => 'autor-evento@ambiente.local',
        ]);
        $event = Event::factory()->published()->for($author, 'author')->create();

        $this->getJson("/api/v1/events/{$event->slug}")
            ->assertOk()
            ->assertJsonPath('data.author.name', 'Autor do Evento')
            ->assertJsonMissingPath('data.author.email')
            ->assertJsonMissingPath('data.author.is_active');
    }

    public function test_vagas_livres_ignore_cancelled_registrations_and_allow_unlimited_capacity(): void
    {
        $limited = Event::factory()->published()->create(['capacity' => 2]);
        EventRegistration::factory()->for($limited)->create(['status' => RegistrationStatus::INSCRITO]);
        EventRegistration::factory()->for($limited)->cancelled()->create();

        $withPresent = Event::factory()->published()->create(['capacity' => 3]);
        EventRegistration::factory()->for($withPresent)->present()->create();

        $unlimited = Event::factory()->published()->create(['capacity' => null]);

        $events = collect($this->getJson('/api/v1/events')->assertOk()->json('data'))->keyBy('id');

        $this->assertSame(1, $events->get($limited->id)['vagas_livres']);
        $this->assertSame(2, $events->get($withPresent->id)['vagas_livres']);
        $this->assertNull($events->get($unlimited->id)['vagas_livres']);

        $this->getJson("/api/v1/events/{$limited->slug}")
            ->assertOk()
            ->assertJsonPath('data.vagas_livres', 1);
    }

    public function test_moderator_creates_updates_and_deletes_events_with_a_cover(): void
    {
        Storage::fake('public');

        $moderator = $this->actingAsRole(RoleName::MODERADOR);
        $payload = $this->eventPayload([
            'title' => 'Oficina de compostagem',
            'status' => EventStatus::PUBLICADO->value,
        ]);
        $payload['cover'] = UploadedFile::fake()->image('capa.jpg', 10, 10);

        $response = $this->postJson('/api/v1/events', $payload)
            ->assertCreated()
            ->assertJsonPath('data.title', 'Oficina de compostagem')
            ->assertJsonPath('data.status', EventStatus::PUBLICADO->value);

        $event = Event::query()->findOrFail($response->json('data.id'));

        $this->assertSame($moderator->getKey(), $event->author_id);
        $this->assertStringStartsWith('covers/', (string) $event->cover_image);
        Storage::disk('public')->assertExists($event->cover_image);

        $this->putJson("/api/v1/events/{$event->getKey()}", [
            'title' => 'Oficina avançada de compostagem',
            'capacity' => 5,
            'status' => EventStatus::CONCLUIDO->value,
        ])->assertOk()
            ->assertJsonPath('data.title', 'Oficina avançada de compostagem')
            ->assertJsonPath('data.capacity', 5);

        $this->assertDatabaseHas('events', [
            'id' => $event->getKey(),
            'title' => 'Oficina avançada de compostagem',
            'status' => EventStatus::CONCLUIDO->value,
        ]);

        $this->deleteJson("/api/v1/events/{$event->getKey()}")->assertNoContent();

        $this->assertSoftDeleted('events', ['id' => $event->getKey()]);
        Storage::disk('public')->assertMissing($event->cover_image);
    }

    public function test_event_management_requires_authentication_and_moderator_policy(): void
    {
        $event = Event::factory()->published()->create();

        $this->postJson('/api/v1/events', $this->eventPayload())
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated');

        $this->actingAsRole(RoleName::RESIDENTE);
        $this->postJson('/api/v1/events', $this->eventPayload())
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');
        $this->putJson("/api/v1/events/{$event->getKey()}", ['title' => 'Alteração'])->assertForbidden();
        $this->deleteJson("/api/v1/events/{$event->getKey()}")->assertForbidden();
    }

    public function test_cover_upload_rejects_unsupported_files(): void
    {
        $this->actingAsRole(RoleName::MODERADOR);

        $payload = $this->eventPayload([
            'cover' => UploadedFile::fake()->create('capa.txt', 10, 'text/plain'),
        ]);

        $this->postJson('/api/v1/events', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonStructure(['status', 'code', 'message', 'errors' => ['cover']]);
    }

    public function test_registration_duplicate_capacity_and_reactivation_use_the_same_row(): void
    {
        $resident = $this->actingAsRole(RoleName::RESIDENTE);
        $event = Event::factory()->published()->create([
            'capacity' => 1,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
        ]);

        $first = $this->postJson("/api/v1/events/{$event->getKey()}/register")
            ->assertCreated()
            ->assertJsonPath('data.status', RegistrationStatus::INSCRITO->value);

        $registrationId = $first->json('data.id');

        $this->postJson("/api/v1/events/{$event->getKey()}/register")
            ->assertConflict()
            ->assertJsonPath('code', 'conflict');

        $other = User::factory()->create();
        $this->actingAs($other, 'sanctum')
            ->postJson("/api/v1/events/{$event->getKey()}/register")
            ->assertConflict()
            ->assertJsonPath('code', 'conflict');

        $this->actingAs($resident, 'sanctum')
            ->deleteJson("/api/v1/events/{$event->getKey()}/register")
            ->assertOk()
            ->assertJsonPath('data.status', RegistrationStatus::CANCELADO->value);

        $this->assertDatabaseHas('event_registrations', [
            'id' => $registrationId,
            'status' => RegistrationStatus::CANCELADO->value,
        ]);

        $this->postJson("/api/v1/events/{$event->getKey()}/register")
            ->assertCreated()
            ->assertJsonPath('data.id', $registrationId)
            ->assertJsonPath('data.status', RegistrationStatus::INSCRITO->value);

        $this->assertSame(1, EventRegistration::query()->count());
    }

    public function test_registration_requires_a_published_future_event(): void
    {
        $this->actingAsRole(RoleName::RESIDENTE);

        $draft = Event::factory()->create(['starts_at' => now()->addDay()]);
        $past = Event::factory()->published()->create([
            'starts_at' => now()->subDay(),
            'ends_at' => now()->subHours(20),
        ]);

        $this->postJson("/api/v1/events/{$draft->getKey()}/register")->assertForbidden();
        $this->postJson("/api/v1/events/{$past->getKey()}/register")
            ->assertConflict()
            ->assertJsonPath('code', 'conflict');
        $this->postJson('/api/v1/events/999999/register')->assertNotFound();
    }

    public function test_moderator_lists_registrations_and_marks_presence_after_start(): void
    {
        $event = Event::factory()->published()->create([
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
        ]);

        $this->getJson("/api/v1/events/{$event->getKey()}/registrations")->assertUnauthorized();

        $resident = $this->actingAsRole(RoleName::RESIDENTE);
        $registration = $this->postJson("/api/v1/events/{$event->getKey()}/register")
            ->assertCreated()
            ->json('data');

        $this->getJson("/api/v1/events/{$event->getKey()}/registrations")->assertForbidden();
        $this->patchJson("/api/v1/event-registrations/{$registration['id']}")->assertForbidden();

        $this->actingAsRole(RoleName::MODERADOR);
        $this->getJson("/api/v1/events/{$event->getKey()}/registrations")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user.id', $resident->getKey());

        $this->patchJson("/api/v1/event-registrations/{$registration['id']}")
            ->assertConflict()
            ->assertJsonPath('code', 'conflict');

        $event->update(['starts_at' => now()->subMinute()]);

        $this->patchJson("/api/v1/event-registrations/{$registration['id']}")
            ->assertOk()
            ->assertJsonPath('data.status', RegistrationStatus::PRESENTE->value);

        $this->assertDatabaseHas('event_registrations', [
            'id' => $registration['id'],
            'status' => RegistrationStatus::PRESENTE->value,
        ]);

        $this->patchJson('/api/v1/event-registrations/999999')->assertNotFound();
    }

    public function test_resident_registration_history_is_paginated_and_filterable(): void
    {
        $this->getJson('/api/v1/me/registrations')->assertUnauthorized();

        $resident = $this->actingAsRole(RoleName::RESIDENTE);
        $upcoming = Event::factory()->published()->create();
        $past = Event::factory()->concluded()->create();

        EventRegistration::factory()->for($upcoming)->for($resident)->create();
        EventRegistration::factory()->for($past)->for($resident)->cancelled()->create();
        EventRegistration::factory()->create();

        $response = $this->getJson('/api/v1/me/registrations')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'status', 'event' => ['id', 'title']]],
                'meta' => ['total', 'per_page'],
            ]);

        $titles = array_column(array_column($response->json('data'), 'event'), 'title');
        $this->assertContains($upcoming->title, $titles);
        $this->assertContains($past->title, $titles);

        $this->getJson('/api/v1/me/registrations?filter[status]=cancelado')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', RegistrationStatus::CANCELADO->value);
    }

    private function eventPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Evento de teste',
            'description' => 'Descrição do evento de teste.',
            'location' => 'Praça central do bairro',
            'starts_at' => now()->addDays(2)->setTime(9, 0)->format('Y-m-d H:i:s'),
            'ends_at' => now()->addDays(2)->setTime(12, 0)->format('Y-m-d H:i:s'),
            'capacity' => 20,
            'status' => EventStatus::PUBLICADO->value,
        ], $overrides);
    }
}
