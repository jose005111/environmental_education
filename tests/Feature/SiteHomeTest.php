<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Content;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteHomeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_home_is_public_and_returns_empty_editor_blocks_until_configured(): void
    {
        $this->getJson('/api/v1/public/home')->assertOk()
            ->assertJsonPath('hero.title', '')
            ->assertJsonPath('stats.residents', 0)
            ->assertJsonPath('settings.site_name', '')
            ->assertJsonCount(0, 'featured_contents');
    }

    public function test_only_admin_can_edit_and_published_content_appears_in_home(): void
    {
        $content = Content::factory()->published()->create();
        $draft = Content::factory()->create();
        $payload = [
            'hero' => [
                'title' => 'Bairro dos Navegantes',
                'subtitle' => 'Cuidar do ambiente juntos',
                'image_url' => 'https://example.test/hero.webp',
                'image_alt' => 'Moradores junto ao rio',
                'video_url' => null,
                'primary_cta' => ['label' => 'Criar conta', 'href' => '/registar'],
                'secondary_cta' => ['label' => 'Aprender', 'href' => '/aprender'],
            ],
            'how_it_works' => [['title' => 'Aprende', 'text' => 'Lê conteúdos.', 'icon' => 'book']],
            'settings' => [
                'site_name' => 'Bairro dos Navegantes',
                'contact_email' => 'bairro@example.test',
                'social' => [],
                'footer_text' => 'Juntos pelo bairro.',
            ],
            'featured_content_ids' => [$content->id],
        ];

        $this->putJson('/api/v1/admin/site', $payload)->assertUnauthorized();

        $resident = User::factory()->create(['role_id' => Role::query()->where('name', RoleName::RESIDENTE)->firstOrFail()->id]);
        $this->actingAs($resident, 'sanctum')->putJson('/api/v1/admin/site', $payload)->assertForbidden();

        $admin = User::factory()->create(['role_id' => Role::query()->where('name', RoleName::ADMINISTRADOR)->firstOrFail()->id]);
        $this->actingAs($admin, 'sanctum')->putJson('/api/v1/admin/site', [...$payload, 'featured_content_ids' => [$draft->id]])
            ->assertUnprocessable()->assertJsonValidationErrors('featured_content_ids');

        $this->actingAs($admin, 'sanctum')->putJson('/api/v1/admin/site', $payload)->assertOk()
            ->assertJsonPath('hero.title', 'Bairro dos Navegantes');

        $this->getJson('/api/v1/public/home')->assertOk()
            ->assertJsonPath('hero.title', 'Bairro dos Navegantes')
            ->assertJsonPath('featured_contents.0.slug', $content->slug)
            ->assertJsonPath('featured_contents.0.category.slug', $content->category->slug)
            ->assertJsonPath('stats.residents', 3)
            ->assertJsonCount(1, 'featured_contents');
    }
}
