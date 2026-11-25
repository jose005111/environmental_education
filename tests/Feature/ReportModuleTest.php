<?php

namespace Tests\Feature;

use App\Enums\ReportStatus;
use App\Enums\RoleName;
use App\Models\Category;
use App\Models\Report;
use App\Models\ReportUpdate;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ZoneSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(ZoneSeeder::class);
        Storage::fake('public');
    }

    public function test_resident_creates_report_with_photos_and_first_update(): void
    {
        $resident = $this->actingAsRole(RoleName::RESIDENTE);
        $category = Category::factory()->create();

        $response = $this->postJson('/api/v1/reports', [
            'zone_id' => Zone::firstOrFail()->id,
            'category_id' => $category->id,
            'title' => 'Lixo acumulado na praça',
            'description' => 'Vários sacos de lixo foram abandonados junto ao banco.',
            'location_note' => 'Junto ao banco da praça',
            'latitude' => '-22.9123456',
            'longitude' => '-43.1234567',
            'photos' => [
                UploadedFile::fake()->image('primeira.jpg'),
                UploadedFile::fake()->image('segunda.png'),
            ],
        ])->assertCreated()
            ->assertJsonPath('data.status', 'recebido')
            ->assertJsonPath('data.is_public', false)
            ->assertJsonCount(2, 'data.photos')
            ->assertJsonCount(1, 'data.updates');

        $report = Report::firstOrFail();

        $this->assertSame($resident->id, $report->user_id);
        $this->assertSame(ReportStatus::RECEBIDO, $report->status);
        $this->assertNull($report->resolved_at);
        $this->assertSame(2, $report->photos()->count());

        foreach ($report->photos as $photo) {
            Storage::disk('public')->assertExists($photo->path);
        }

        $update = $report->updates()->firstOrFail();

        $this->assertSame($resident->id, $update->user_id);
        $this->assertNull($update->from_status);
        $this->assertSame(ReportStatus::RECEBIDO, $update->to_status);
        $this->assertNotNull($response->json('data.updates.0.id'));
    }

    public function test_photo_limit_and_upload_validation(): void
    {
        $this->actingAsRole(RoleName::RESIDENTE);

        $data = [
            'zone_id' => Zone::firstOrFail()->id,
            'title' => 'Reporte com fotos',
            'description' => 'Descrição do problema ambiental.',
        ];

        $tooMany = array_fill(0, (int) config('uploads.report_photos_limit', 5) + 1, UploadedFile::fake()->image('foto.jpg'));

        $this->postJson('/api/v1/reports', $data + ['photos' => $tooMany])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('photos');

        $this->postJson('/api/v1/reports', $data + [
            'photos' => [UploadedFile::fake()->create('foto.txt', 10, 'text/plain')],
        ])->assertUnprocessable()->assertJsonValidationErrors('photos.0');

        $this->postJson('/api/v1/reports', $data + [
            'photos' => [UploadedFile::fake()->image('grande.jpg')->size(6000)],
        ])->assertUnprocessable()->assertJsonValidationErrors('photos.0');

        $this->assertSame(0, Report::count());
    }

    public function test_public_list_only_contains_resolved_public_reports_without_author_or_location(): void
    {
        $author = User::factory()->create([
            'email' => 'autor-publico@example.test',
            'phone' => '99999123456',
        ]);

        $public = Report::factory()->resolved()->for($author)->create([
            'title' => 'Caso resolvido e público',
            'latitude' => '-22.9123456',
            'longitude' => '-43.1234567',
            'location_note' => 'Praça central',
        ]);

        Report::factory()->resolved()->for($author)->create(['is_public' => false]);
        Report::factory()->for($author)->create(['is_public' => true]);

        $response = $this->getJson('/api/v1/reports/public')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $public->id);

        $data = $response->json('data.0');

        foreach (['user_id', 'author', 'email', 'phone', 'latitude', 'longitude', 'assigned_to'] as $key) {
            $this->assertArrayNotHasKey($key, $data);
        }

        $content = (string) $response->getContent();

        $this->assertStringNotContainsString($author->email, $content);
        $this->assertStringNotContainsString($author->phone, $content);
        $this->assertStringNotContainsString('-22.9123456', $content);
        $this->assertStringNotContainsString('-43.1234567', $content);

        $detail = $this->getJson("/api/v1/reports/{$public->id}")->assertOk();

        $this->assertArrayNotHasKey('user_id', $detail->json('data'));
        $this->assertArrayNotHasKey('email', $detail->json('data'));
        $this->assertArrayNotHasKey('phone', $detail->json('data'));
        $this->assertArrayNotHasKey('latitude', $detail->json('data'));
        $this->assertArrayNotHasKey('longitude', $detail->json('data'));
        $this->assertStringNotContainsString($author->email, (string) $detail->getContent());
    }

    public function test_resident_only_sees_own_reports_and_cannot_manage_reports(): void
    {
        $resident = $this->actingAsRole(RoleName::RESIDENTE);
        $other = User::factory()->create();

        $mine = Report::factory()->for($resident)->create(['title' => 'Meu reporte']);
        $otherReport = Report::factory()->for($other)->create(['title' => 'Reporte alheio']);
        ReportUpdate::factory()->for($mine)->create();

        $this->getJson("/api/v1/reports/{$otherReport->id}")->assertForbidden();
        $this->getJson('/api/v1/reports')->assertForbidden();

        $this->getJson('/api/v1/me/reports')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id)
            ->assertJsonCount(1, 'data.0.updates');

        $this->patchJson("/api/v1/reports/{$mine->id}/assign", [
            'assigned_to' => User::factory()->moderator()->create()->id,
        ])->assertForbidden();

        $this->patchJson("/api/v1/reports/{$mine->id}/status", [
            'status' => ReportStatus::EM_ANALISE->value,
        ])->assertForbidden();
    }

    public function test_moderator_index_filters_zone_status_assignee_and_category(): void
    {
        $this->actingAsRole(RoleName::MODERADOR);

        $zone = Zone::firstOrFail();
        $otherZone = Zone::whereKeyNot($zone->id)->firstOrFail();
        $category = Category::factory()->create();
        $otherCategory = Category::factory()->create();
        $assignee = User::factory()->moderator()->create();

        $matching = Report::factory()->for(User::factory()->create())->create([
            'zone_id' => $zone->id,
            'category_id' => $category->id,
            'status' => ReportStatus::EM_ANALISE,
            'assigned_to' => $assignee->id,
            'title' => 'Reporte filtrado',
        ]);

        Report::factory()->create([
            'zone_id' => $otherZone->id,
            'category_id' => $category->id,
            'status' => ReportStatus::EM_ANALISE,
            'assigned_to' => $assignee->id,
        ]);

        Report::factory()->create([
            'zone_id' => $zone->id,
            'category_id' => $otherCategory->id,
            'status' => ReportStatus::EM_ANALISE,
            'assigned_to' => $assignee->id,
        ]);

        Report::factory()->create([
            'zone_id' => $zone->id,
            'category_id' => $category->id,
            'status' => ReportStatus::RECEBIDO,
            'assigned_to' => $assignee->id,
        ]);

        $response = $this->getJson('/api/v1/reports?'.http_build_query([
            'filter' => [
                'zone' => $zone->id,
                'status' => ReportStatus::EM_ANALISE->value,
                'assigned' => $assignee->id,
                'category' => $category->id,
            ],
            'per_page' => 2,
        ]))->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matching->id)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.per_page', 2);

        $this->assertArrayNotHasKey('latitude', $response->json('data.0'));
        $this->assertArrayNotHasKey('description', $response->json('data.0'));
    }

    public function test_moderator_can_assign_and_unassign_a_report(): void
    {
        $this->actingAsRole(RoleName::MODERADOR);

        $report = Report::factory()->create();
        $assignee = User::factory()->moderator()->create();

        $this->patchJson("/api/v1/reports/{$report->id}/assign", [
            'assigned_to' => $assignee->id,
        ])->assertOk()->assertJsonPath('data.assigned_to', $assignee->id);

        $this->assertDatabaseHas('reports', [
            'id' => $report->id,
            'assigned_to' => $assignee->id,
        ]);

        $this->assertSame(0, $report->updates()->count());

        $this->patchJson("/api/v1/reports/{$report->id}/assign", [
            'assigned_to' => 999999,
        ])->assertUnprocessable()->assertJsonValidationErrors('assigned_to');

        $this->patchJson("/api/v1/reports/{$report->id}/assign", [
            'assigned_to' => null,
        ])->assertOk()->assertJsonPath('data.assigned_to', null);

        $this->assertNull($report->fresh()->assigned_to);
    }

    public function test_valid_transitions_record_updates_and_resolved_at(): void
    {
        $moderator = $this->actingAsRole(RoleName::MODERADOR);

        $report = Report::factory()->for(User::factory()->create())->create([
            'status' => ReportStatus::RECEBIDO,
            'is_public' => false,
        ]);

        ReportUpdate::factory()->for($report)->create();

        $this->patchJson("/api/v1/reports/{$report->id}/status", [
            'status' => ReportStatus::EM_ANALISE->value,
            'note' => 'Triagem inicial',
        ])->assertOk()
            ->assertJsonPath('data.status', ReportStatus::EM_ANALISE->value)
            ->assertJsonCount(2, 'data.updates');

        $transition = $report->updates()->latest('id')->firstOrFail();

        $this->assertSame(ReportStatus::RECEBIDO, $transition->from_status);
        $this->assertSame(ReportStatus::EM_ANALISE, $transition->to_status);
        $this->assertSame($moderator->id, $transition->user_id);
        $this->assertSame('Triagem inicial', $transition->note);

        $response = $this->patchJson("/api/v1/reports/{$report->id}/status", [
            'status' => ReportStatus::RESOLVIDO->value,
            'note' => 'Problema corrigido',
            'is_public' => true,
        ])->assertOk()
            ->assertJsonPath('data.status', ReportStatus::RESOLVIDO->value)
            ->assertJsonPath('data.is_public', true);

        $report->refresh();

        $this->assertSame(ReportStatus::RESOLVIDO, $report->status);
        $this->assertTrue($report->is_public);
        $this->assertNotNull($report->resolved_at);
        $this->assertNotNull($response->json('data.resolved_at'));
        $this->assertSame(3, $report->updates()->count());

        $last = $report->updates()->latest('id')->firstOrFail();

        $this->assertSame(ReportStatus::EM_ANALISE, $last->from_status);
        $this->assertSame(ReportStatus::RESOLVIDO, $last->to_status);
        $this->assertSame('Problema corrigido', $last->note);
    }

    public function test_invalid_transitions_are_rejected_without_changes(): void
    {
        $this->actingAsRole(RoleName::MODERADOR);

        $report = Report::factory()->create(['status' => ReportStatus::RECEBIDO]);
        ReportUpdate::factory()->for($report)->create();

        $this->patchJson("/api/v1/reports/{$report->id}/status", [
            'status' => ReportStatus::RESOLVIDO->value,
        ])->assertUnprocessable()->assertJsonValidationErrors('status');

        $this->patchJson("/api/v1/reports/{$report->id}/status", [
            'status' => ReportStatus::RECEBIDO->value,
        ])->assertUnprocessable()->assertJsonValidationErrors('status');

        $this->patchJson("/api/v1/reports/{$report->id}/status", [
            'status' => 'estado-invalido',
        ])->assertUnprocessable()->assertJsonValidationErrors('status');

        $report->refresh();

        $this->assertSame(ReportStatus::RECEBIDO, $report->status);
        $this->assertNull($report->resolved_at);
        $this->assertSame(1, $report->updates()->count());

        $inAnalysis = Report::factory()->create(['status' => ReportStatus::EM_ANALISE]);

        $this->patchJson("/api/v1/reports/{$inAnalysis->id}/status", [
            'status' => ReportStatus::RECEBIDO->value,
        ])->assertUnprocessable()->assertJsonValidationErrors('status');

        $rejected = Report::factory()->rejected()->create();

        $this->patchJson("/api/v1/reports/{$rejected->id}/status", [
            'status' => ReportStatus::EM_ANALISE->value,
        ])->assertUnprocessable()->assertJsonValidationErrors('status');

        $this->assertSame(ReportStatus::REJEITADO, $rejected->fresh()->status);
    }

    public function test_admin_can_reopen_final_report_and_is_public_only_changes_explicitly(): void
    {
        $admin = $this->actingAsRole(RoleName::ADMINISTRADOR);

        $report = Report::factory()->resolved()->create([
            'is_public' => false,
            'resolved_at' => now()->subDay(),
        ]);

        $this->patchJson("/api/v1/reports/{$report->id}/status", [
            'status' => ReportStatus::EM_ANALISE->value,
        ])->assertOk()
            ->assertJsonPath('data.status', ReportStatus::EM_ANALISE->value)
            ->assertJsonPath('data.is_public', false);

        $report->refresh();

        $this->assertSame(ReportStatus::EM_ANALISE, $report->status);
        $this->assertNull($report->resolved_at);
        $this->assertFalse($report->is_public);

        $update = $report->updates()->latest('id')->firstOrFail();

        $this->assertSame($admin->id, $update->user_id);
        $this->assertSame(ReportStatus::RESOLVIDO, $update->from_status);
        $this->assertSame(ReportStatus::EM_ANALISE, $update->to_status);

        $second = Report::factory()->inAnalysis()->create(['is_public' => false]);

        $this->patchJson("/api/v1/reports/{$second->id}/status", [
            'status' => ReportStatus::RESOLVIDO->value,
        ])->assertOk()->assertJsonPath('data.is_public', false);

        $this->assertFalse($second->fresh()->is_public);

        $this->patchJson("/api/v1/reports/{$second->id}/status", [
            'status' => ReportStatus::EM_ANALISE->value,
            'is_public' => true,
        ])->assertOk()
            ->assertJsonPath('data.status', ReportStatus::EM_ANALISE->value)
            ->assertJsonPath('data.is_public', true);

        $this->assertTrue($second->fresh()->is_public);
    }

    public function test_detail_exposes_sensitive_fields_only_to_privileged_roles(): void
    {
        $moderator = $this->actingAsRole(RoleName::MODERADOR);
        $author = User::factory()->create(['phone' => '888777666']);

        $report = Report::factory()->for($author)->create([
            'status' => ReportStatus::EM_ANALISE,
            'assigned_to' => $moderator->id,
            'latitude' => '-22.1111111',
            'longitude' => '-43.1111111',
        ]);

        ReportUpdate::factory()
            ->for($report)
            ->for($moderator)
            ->transition(ReportStatus::RECEBIDO, ReportStatus::EM_ANALISE, 'Nota interna')
            ->create();

        $this->getJson("/api/v1/reports/{$report->id}")
            ->assertOk()
            ->assertJsonPath('data.latitude', '-22.1111111')
            ->assertJsonPath('data.longitude', '-43.1111111')
            ->assertJsonPath('data.phone', '888777666')
            ->assertJsonPath('data.assigned_to', $moderator->id)
            ->assertJsonPath('data.updates.0.note', 'Nota interna')
            ->assertJsonPath('data.updates.0.user.id', $moderator->id);

        $resident = User::factory()->create();
        $this->actingAs($resident, 'sanctum');

        $own = Report::factory()->for($resident)->create([
            'assigned_to' => $moderator->id,
            'latitude' => '-22.2222222',
            'longitude' => '-43.2222222',
        ]);

        ReportUpdate::factory()
            ->for($own)
            ->for($moderator)
            ->transition(ReportStatus::RECEBIDO, ReportStatus::EM_ANALISE, 'Nota interna')
            ->create();

        $response = $this->getJson("/api/v1/reports/{$own->id}")->assertOk();

        $this->assertArrayNotHasKey('latitude', $response->json('data'));
        $this->assertArrayNotHasKey('longitude', $response->json('data'));
        $this->assertArrayNotHasKey('phone', $response->json('data'));
        $this->assertArrayNotHasKey('assigned_to', $response->json('data'));
        $this->assertArrayNotHasKey('note', $response->json('data.updates.0'));
        $this->assertStringNotContainsString('-22.2222222', (string) $response->getContent());
    }

    public function test_authentication_and_missing_report_responses(): void
    {
        $data = [
            'zone_id' => Zone::firstOrFail()->id,
            'title' => 'Sem autenticação',
            'description' => 'Não deveria ser criado.',
        ];

        $this->postJson('/api/v1/reports', $data)->assertUnauthorized();
        $this->getJson('/api/v1/me/reports')->assertUnauthorized();
        $this->patchJson('/api/v1/reports/1/assign', ['assigned_to' => null])->assertUnauthorized();

        $private = Report::factory()->create();
        $this->getJson("/api/v1/reports/{$private->id}")->assertUnauthorized();

        $this->actingAsRole(RoleName::MODERADOR);

        $this->getJson('/api/v1/reports/999999')->assertNotFound();
        $this->patchJson('/api/v1/reports/999999/status', [
            'status' => ReportStatus::EM_ANALISE->value,
        ])->assertNotFound();

        $deleted = Report::factory()->create();
        $deleted->delete();

        $this->getJson("/api/v1/reports/{$deleted->id}")->assertNotFound();
    }
}
