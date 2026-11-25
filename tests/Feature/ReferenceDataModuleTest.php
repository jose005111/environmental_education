<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Category;
use App\Models\Content;
use App\Models\Role;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferenceDataModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_public_lists_use_summary_resources_and_pagination(): void
    {
        Zone::factory()->create(['name' => 'Centro']);
        Zone::factory()->create(['name' => 'Zona Sul']);
        Zone::factory()->create(['name' => 'Zona Leste']);
        Zone::factory()->create(['name' => 'Zona Norte']);
        Category::factory()->create(['name' => 'Reciclagem']);
        Category::factory()->create(['name' => 'Energia']);

        $zones = $this->getJson('/api/v1/zones?search=norte')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure(['data' => [['id', 'name']], 'links', 'meta']);

        $this->assertSame('Zona Norte', $zones->json('data.0.name'));
        $this->assertArrayNotHasKey('description', $zones->json('data.0'));

        $this->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data' => [['id', 'name', 'slug']], 'links', 'meta']);
    }

    public function test_admin_can_manage_zones_and_categories(): void
    {
        $this->actingAsRole(RoleName::ADMINISTRADOR);

        $zone = $this->postJson('/api/v1/zones', [
            'name' => 'Zona Nova',
            'description' => 'Criada pelo administrador.',
        ])->assertCreated()->assertJsonPath('data.name', 'Zona Nova')->json('data');

        $this->putJson("/api/v1/zones/{$zone['id']}", [
            'name' => 'Zona Nova Actualizada',
        ])->assertOk()->assertJsonPath('data.name', 'Zona Nova Actualizada');

        $category = $this->postJson('/api/v1/categories', [
            'name' => 'Compostagem',
            'description' => 'Reaproveitamento de resíduos.',
        ])->assertCreated()->assertJsonPath('data.slug', 'compostagem')->json('data');

        $this->putJson("/api/v1/categories/{$category['id']}", [
            'name' => 'Compostagem Domestica',
        ])->assertOk()->assertJsonPath('data.name', 'Compostagem Domestica');

        $this->deleteJson("/api/v1/zones/{$zone['id']}")->assertNoContent();
        $this->deleteJson("/api/v1/categories/{$category['id']}")->assertNoContent();
    }

    public function test_write_routes_require_admin_role(): void
    {
        $this->postJson('/api/v1/zones', ['name' => 'Sem permissão'])->assertUnauthorized();

        $this->actingAsRole(RoleName::RESIDENTE);

        $this->postJson('/api/v1/zones', ['name' => 'Sem permissão'])->assertForbidden();
        $this->postJson('/api/v1/categories', ['name' => 'Sem permissão'])->assertForbidden();
        $this->getJson('/api/v1/users')->assertForbidden();
    }

    public function test_duplicate_names_are_rejected_and_related_records_block_deletion(): void
    {
        Zone::factory()->create(['name' => 'Centro']);
        $category = Category::factory()->create(['name' => 'Reciclagem']);
        Content::factory()->for($category)->create();

        $this->actingAsRole(RoleName::ADMINISTRADOR);

        $this->postJson('/api/v1/zones', ['name' => 'Centro'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->deleteJson("/api/v1/categories/{$category->id}")
            ->assertStatus(409)
            ->assertJsonPath('code', 'conflict');
    }

    public function test_admin_can_list_and_update_users(): void
    {
        $user = User::factory()->create(['name' => 'Morador Antigo']);
        $moderatorRole = Role::where('name', RoleName::MODERADOR->value)->firstOrFail();

        $this->actingAsRole(RoleName::ADMINISTRADOR);

        $this->getJson('/api/v1/users?search=morador')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $user->id)
            ->assertJsonStructure(['data' => [['id', 'name', 'email', 'role', 'is_active']], 'links', 'meta']);

        $this->patchJson("/api/v1/users/{$user->id}", [
            'role_id' => $moderatorRole->id,
            'is_active' => false,
        ])->assertOk()->assertJsonPath('data.role.name', 'moderador');

        $user->refresh();

        $this->assertTrue($user->isModerator());
        $this->assertFalse($user->is_active);
    }

    public function test_user_update_rejects_unknown_role(): void
    {
        $user = User::factory()->create();

        $this->actingAsRole(RoleName::ADMINISTRADOR);

        $this->patchJson("/api/v1/users/{$user->id}", ['role_id' => 999999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role_id');
    }
}
