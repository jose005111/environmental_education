<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Content;
use App\Models\User;
use App\Services\StoredFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ApiInfrastructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_categories_index_uses_summary_resource_pagination_and_filters(): void
    {
        Category::factory()->create(['name' => 'Reciclagem', 'description' => 'Papel e plastico']);
        Category::factory()->create(['name' => 'Energia', 'description' => 'Fontes renovaveis']);
        Category::factory()->create(['name' => 'Agua', 'description' => 'Consumo consciente']);

        $response = $this->getJson('/api/v1/categories?filter[name]=recic&per_page=2')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'name', 'slug']],
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'from', 'last_page', 'per_page', 'to', 'total'],
            ]);

        $response->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Reciclagem')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 1);

        $this->assertArrayNotHasKey('description', $response->json('data.0'));
    }

    public function test_categories_index_supports_search_and_whitelisted_sort(): void
    {
        Category::factory()->create(['name' => 'Reciclagem', 'description' => 'Papel e plastico']);
        Category::factory()->create(['name' => 'Energia', 'description' => 'Fontes renovaveis']);
        Category::factory()->create(['name' => 'Agua', 'description' => 'Consumo consciente']);

        $this->getJson('/api/v1/categories?search=renovaveis')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Energia');

        $names = array_column($this->getJson('/api/v1/categories?sort=-name')->assertOk()->json('data'), 'name');

        $this->assertSame(['Reciclagem', 'Energia', 'Agua'], $names);

        $this->getJson('/api/v1/categories?sort=status')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sort');

        $this->getJson('/api/v1/categories?filter[inexistente]=valor')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('filter.inexistente');
    }

    public function test_category_show_uses_the_complete_resource(): void
    {
        $category = Category::factory()->create(['name' => 'Reciclagem', 'description' => 'Separação de resíduos']);
        Content::factory()->count(2)->for($category)->create();

        $this->getJson("/api/v1/categories/{$category->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $category->id)
            ->assertJsonPath('data.contents_count', 2)
            ->assertJsonStructure(['data' => ['id', 'name', 'slug', 'description', 'contents_count', 'contents', 'created_at']]);
    }

    public function test_validation_errors_follow_the_single_api_error_format(): void
    {
        $this->getJson('/api/v1/categories?per_page=999')
            ->assertUnprocessable()
            ->assertJsonPath('status', 422)
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonPath('message', 'Os dados fornecidos são inválidos.')
            ->assertJsonStructure(['status', 'code', 'message', 'errors' => ['per_page']]);
    }

    public function test_authentication_authorization_and_not_found_errors_follow_the_single_format(): void
    {
        $this->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated')
            ->assertJsonPath('message', 'Autenticação necessária.');

        $this->getJson('/api/v1/categories/999999')
            ->assertNotFound()
            ->assertJsonPath('code', 'not_found')
            ->assertJsonPath('message', 'Recurso não encontrado.');

        Route::middleware(['auth:sanctum', 'role:administrador'])
            ->get('/api/v1/test-forbidden', fn () => response()->json(['ok' => true]));

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/v1/test-forbidden')
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden')
            ->assertJsonPath('message', 'Sem permissão para executar esta acção.');
    }

    public function test_server_errors_are_generic_and_do_not_leak_exception_details(): void
    {
        Route::get('/api/v1/test-server-error', fn () => throw new RuntimeException('segredo'));

        $response = $this->getJson('/api/v1/test-server-error')
            ->assertStatus(500)
            ->assertJsonPath('status', 500)
            ->assertJsonPath('code', 'server_error')
            ->assertJsonPath('message', 'Erro interno do servidor.');

        $this->assertStringNotContainsString('segredo', (string) $response->getContent());
    }

    public function test_general_api_rate_limit_is_sixty_requests_per_minute(): void
    {
        for ($request = 0; $request < 60; $request++) {
            $this->getJson('/api/v1/categories')->assertOk();
        }

        $this->getJson('/api/v1/categories')
            ->assertStatus(429)
            ->assertJsonPath('code', 'too_many_requests')
            ->assertJsonPath('message', 'Demasiados pedidos. Tente novamente mais tarde.');
    }

    public function test_stored_file_validates_stores_and_deletes(): void
    {
        Storage::fake('public');

        $service = app(StoredFile::class);
        $stored = $service->store(UploadedFile::fake()->image('foto.jpg', 10, 10), 'reports');

        Storage::disk('public')->assertExists($stored['path']);
        $this->assertStringContainsString('/storage/reports/', $stored['url']);

        $service->delete($stored['path']);

        Storage::disk('public')->assertMissing($stored['path']);
    }

    public function test_stored_file_rejects_invalid_files_and_directories(): void
    {
        Storage::fake('public');

        $service = app(StoredFile::class);

        try {
            $service->store(UploadedFile::fake()->create('foto.txt', 10, 'text/plain'), 'reports');
            $this->fail('Ficheiro inválido deveria ser rejeitado.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        try {
            $service->store(UploadedFile::fake()->image('grande.jpg')->size(6000), 'reports');
            $this->fail('Ficheiro maior que 5 MB deveria ser rejeitado.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $this->expectException(InvalidArgumentException::class);
        $service->store(UploadedFile::fake()->image('foto.jpg'), 'invalido');
    }

    public function test_deleting_a_model_removes_its_stored_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('covers/teste.jpg', 'conteudo');

        $content = Content::factory()->create(['cover_image' => 'covers/teste.jpg']);
        $content->delete();

        Storage::disk('public')->assertMissing('covers/teste.jpg');
    }

    public function test_cors_origins_are_explicit(): void
    {
        $origins = config('cors.allowed_origins');

        $this->assertIsArray($origins);
        $this->assertNotContains('*', $origins);
        $this->assertContains('http://localhost:3000', $origins);
    }

    public function test_request_id_is_returned_and_log_context_excludes_query_secrets(): void
    {
        $context = [];
        $logger = Mockery::mock();
        $logger->shouldReceive('info')
            ->once()
            ->with('api.request', Mockery::on(function (array $data) use (&$context): bool {
                $context = $data;

                return true;
            }));

        Log::shouldReceive('channel')->once()->andReturn($logger);

        $response = $this->getJson('/api/v1/categories?token=segredo', ['X-Request-Id' => 'req-123'])
            ->assertOk()
            ->assertHeader('X-Request-Id', 'req-123');

        $this->assertSame('req-123', $context['request_id']);
        $this->assertSame('api/v1/categories', $context['path']);
        $this->assertStringNotContainsString('segredo', json_encode($context) ?: '');
        $this->assertSame(200, $context['status']);
    }
}
