<?php

namespace Tests\Feature;

use App\Enums\ContentType;
use App\Enums\RoleName;
use App\Models\Category;
use App\Models\Content;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContentModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_public_index_lists_only_published_contents_with_filters_and_pagination(): void
    {
        $category = Category::factory()->create();
        $otherCategory = Category::factory()->create();

        Content::factory()->count(3)->published()->for($category)->create(['type' => ContentType::ARTIGO]);
        Content::factory()->published()->for($otherCategory)->create(['type' => ContentType::DICA]);
        Content::factory()->for($category)->create(['type' => ContentType::ARTIGO]);
        Content::factory()->published()->for($category)->create([
            'type' => ContentType::ARTIGO,
            'published_at' => null,
        ]);

        $response = $this->getJson('/api/v1/contents?filter[category]='.$category->id.'&filter[type]=artigo&per_page=2')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'title', 'slug', 'summary', 'type', 'status', 'published_at', 'views_count']],
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'from', 'last_page', 'per_page', 'to', 'total'],
            ]);

        $response->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('data.0.status', 'publicado');

        $this->assertArrayNotHasKey('body', $response->json('data.0'));

        $this->getJson('/api/v1/contents?filter[type]=dica')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_public_index_rejects_invalid_query_parameters(): void
    {
        $this->getJson('/api/v1/contents?per_page=51')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');

        $this->getJson('/api/v1/contents?filter[inexistente]=valor')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('filter.inexistente');

        $this->getJson('/api/v1/contents?sort=inexistente')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sort');
    }

    public function test_public_detail_increments_views_count(): void
    {
        $content = Content::factory()->published()->create([
            'title' => 'Reciclagem urbana',
            'body' => 'Guia completo para separar resíduos.',
        ]);

        $this->getJson("/api/v1/contents/{$content->slug}")
            ->assertOk()
            ->assertJsonPath('data.views_count', 1)
            ->assertJsonPath('data.body', 'Guia completo para separar resíduos.')
            ->assertJsonPath('data.category.id', $content->category_id);

        $this->getJson("/api/v1/contents/{$content->slug}")
            ->assertOk()
            ->assertJsonPath('data.views_count', 2);

        $this->assertSame(2, $content->refresh()->views_count);
    }

    public function test_draft_content_is_not_publicly_visible(): void
    {
        $content = Content::factory()->create();

        $this->getJson("/api/v1/contents/{$content->slug}")->assertNotFound();
    }

    public function test_moderator_can_create_content_with_optional_cover(): void
    {
        Storage::fake('public');

        $user = $this->actingAsRole(RoleName::MODERADOR);
        $category = Category::factory()->create();

        $response = $this->postJson('/api/v1/contents', $this->payload($category, [
            'cover_image' => UploadedFile::fake()->image('capa.jpg', 10, 10),
        ]))
            ->assertCreated()
            ->assertJsonPath('data.title', 'Reciclagem urbana')
            ->assertJsonPath('data.status', 'rascunho')
            ->assertJsonPath('data.author.id', $user->id);

        $content = Content::findOrFail($response->json('data.id'));

        $this->assertSame($user->id, $content->author_id);
        $this->assertSame('rascunho', $content->status->value);
        $this->assertNull($content->published_at);
        Storage::disk('public')->assertExists($content->cover_image);
        $this->assertStringContainsString($content->cover_image, $response->json('data.cover_image'));
    }

    public function test_store_validates_required_fields_and_references(): void
    {
        $this->actingAsRole(RoleName::MODERADOR);

        $this->postJson('/api/v1/contents', [])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonStructure(['errors' => ['category_id', 'title', 'summary', 'type']]);

        $category = Category::factory()->create();

        $this->postJson('/api/v1/contents', $this->payload($category, [
            'category_id' => 999999,
            'type' => 'invalido',
        ]))
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['category_id', 'type']]);
    }

    public function test_moderator_can_update_content_and_replace_cover(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('covers/antiga.jpg', 'conteudo antigo');

        $content = Content::factory()->create([
            'title' => 'Título antigo',
            'cover_image' => 'covers/antiga.jpg',
        ]);

        $this->actingAsRole(RoleName::MODERADOR);

        $this->putJson("/api/v1/contents/{$content->id}", [
            'title' => 'Título novo',
            'cover_image' => UploadedFile::fake()->image('nova.png', 10, 10),
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Título novo')
            ->assertJsonPath('data.summary', $content->summary);

        $content->refresh();

        Storage::disk('public')->assertMissing('covers/antiga.jpg');
        Storage::disk('public')->assertExists($content->cover_image);
        $this->assertNotSame('covers/antiga.jpg', $content->cover_image);
    }

    public function test_publish_sets_published_at_on_first_publish_and_updates_on_republish(): void
    {
        $content = Content::factory()->create();
        $this->actingAsRole(RoleName::MODERADOR);

        $this->patchJson("/api/v1/contents/{$content->id}/publish", ['status' => 'publicado'])
            ->assertOk()
            ->assertJsonPath('data.status', 'publicado');

        $firstPublishedAt = $content->refresh()->published_at;
        $this->assertNotNull($firstPublishedAt);

        $this->travel(2)->minutes();

        $this->patchJson("/api/v1/contents/{$content->id}/publish", ['status' => 'publicado'])
            ->assertOk()
            ->assertJsonPath('data.status', 'publicado');

        $republishedAt = $content->refresh()->published_at;
        $this->assertTrue($republishedAt->gt($firstPublishedAt));

        $this->patchJson("/api/v1/contents/{$content->id}/publish", ['status' => 'rascunho'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rascunho');

        $this->assertSame($republishedAt->timestamp, $content->refresh()->published_at->timestamp);
    }

    public function test_publish_validates_status(): void
    {
        $content = Content::factory()->create();
        $this->actingAsRole(RoleName::MODERADOR);

        $this->patchJson("/api/v1/contents/{$content->id}/publish", ['status' => 'arquivado'])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['status']]);

        $this->patchJson("/api/v1/contents/{$content->id}/publish", [])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['status']]);
    }

    public function test_moderator_can_soft_delete_content_and_cover_file_is_removed(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('covers/apagar.jpg', 'conteudo');

        $content = Content::factory()->create(['cover_image' => 'covers/apagar.jpg']);

        $this->actingAsRole(RoleName::MODERADOR);

        $this->deleteJson("/api/v1/contents/{$content->id}")
            ->assertNoContent();

        $this->assertSoftDeleted($content);
        Storage::disk('public')->assertMissing('covers/apagar.jpg');
    }

    public function test_cover_upload_rejects_invalid_mime_type_and_size(): void
    {
        $this->actingAsRole(RoleName::MODERADOR);
        $category = Category::factory()->create();

        $this->postJson('/api/v1/contents', $this->payload($category, [
            'cover_image' => UploadedFile::fake()->create('capa.txt', 10, 'text/plain'),
        ]))
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['cover_image']]);

        $this->postJson('/api/v1/contents', $this->payload($category, [
            'cover_image' => UploadedFile::fake()->image('grande.jpg')->size(6000),
        ]))
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['cover_image']]);

        $this->assertSame(0, Content::count());
    }

    public function test_unauthenticated_writes_are_rejected(): void
    {
        $content = Content::factory()->create();

        $this->postJson('/api/v1/contents', $this->payload($content->category))
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated');

        $this->putJson("/api/v1/contents/{$content->id}", ['title' => 'Novo título'])
            ->assertUnauthorized();

        $this->patchJson("/api/v1/contents/{$content->id}/publish", ['status' => 'publicado'])
            ->assertUnauthorized();

        $this->deleteJson("/api/v1/contents/{$content->id}")
            ->assertUnauthorized();
    }

    public function test_resident_cannot_manage_contents(): void
    {
        $this->actingAsRole(RoleName::RESIDENTE);
        $content = Content::factory()->create();
        $category = Category::factory()->create();

        $this->postJson('/api/v1/contents', $this->payload($category))
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');

        $this->putJson("/api/v1/contents/{$content->id}", ['title' => 'Novo título'])
            ->assertForbidden();

        $this->patchJson("/api/v1/contents/{$content->id}/publish", ['status' => 'publicado'])
            ->assertForbidden();

        $this->deleteJson("/api/v1/contents/{$content->id}")
            ->assertForbidden();
    }

    public function test_missing_contents_return_not_found(): void
    {
        $this->actingAsRole(RoleName::MODERADOR);

        $this->getJson('/api/v1/contents/inexistente')
            ->assertNotFound()
            ->assertJsonPath('code', 'not_found');

        $this->putJson('/api/v1/contents/999999', ['title' => 'Novo título'])
            ->assertNotFound();

        $this->patchJson('/api/v1/contents/999999/publish', ['status' => 'publicado'])
            ->assertNotFound();

        $this->deleteJson('/api/v1/contents/999999')
            ->assertNotFound();
    }

    private function payload(Category $category, array $overrides = []): array
    {
        return array_merge([
            'category_id' => $category->id,
            'title' => 'Reciclagem urbana',
            'summary' => 'Como separar resíduos em casa.',
            'body' => 'Guia completo para separar resíduos.',
            'type' => ContentType::ARTIGO->value,
        ], $overrides);
    }
}
