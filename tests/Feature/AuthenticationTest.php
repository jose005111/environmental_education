<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Role;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ZoneSeeder;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(ZoneSeeder::class);
    }

    public function test_register_always_creates_a_resident_and_returns_a_token(): void
    {
        Notification::fake();

        $adminRole = Role::where('name', RoleName::ADMINISTRADOR->value)->firstOrFail();
        $residentRole = Role::where('name', RoleName::RESIDENTE->value)->firstOrFail();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Novo Residente',
            'email' => 'novo@ambiente.local',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'zone_id' => Zone::firstOrFail()->id,
            'role_id' => $adminRole->id,
            'is_active' => false,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.role.name', 'residente')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonStructure(['token']);

        $this->assertDatabaseHas('users', [
            'email' => 'novo@ambiente.local',
            'role_id' => $residentRole->id,
            'is_active' => 1,
        ]);

        $user = User::where('email', 'novo@ambiente.local')->firstOrFail();
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_register_rejects_invalid_payload(): void
    {
        $this->postJson('/api/v1/auth/register', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_login_returns_a_token_and_validation_errors_are_json(): void
    {
        $user = User::factory()->create([
            'email' => 'login@ambiente.local',
            'password' => 'password123',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'errada',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonStructure(['token']);
    }

    public function test_inactive_accounts_cannot_login(): void
    {
        $user = User::factory()->inactive()->create([
            'email' => 'inativo@ambiente.local',
            'password' => 'password123',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertForbidden()->assertJsonPath('message', 'Esta conta está desactivada.');
    }

    public function test_logout_revokes_only_the_current_token(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken('api');
        $other = $user->createToken('outro-dispositivo');

        $this->withToken($current->plainTextToken)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Sessão terminada com sucesso.');

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $current->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $other->accessToken->id]);
    }

    public function test_logout_requires_authentication(): void
    {
        $this->postJson('/api/v1/auth/logout')->assertUnauthorized();
    }

    public function test_me_requires_authentication_and_only_updates_profile_fields(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();

        $user = $this->actingAsRole(RoleName::RESIDENTE);
        $adminRole = Role::where('name', RoleName::ADMINISTRADOR->value)->firstOrFail();

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.role.name', 'residente');

        $this->putJson('/api/v1/me', [
            'name' => 'Nome Actualizado',
            'phone' => '(21) 98888-7777',
            'zone_id' => Zone::firstOrFail()->id,
            'role_id' => $adminRole->id,
            'is_active' => false,
        ])->assertOk()->assertJsonPath('data.name', 'Nome Actualizado');

        $user->refresh();

        $this->assertSame('(21) 98888-7777', $user->phone);
        $this->assertTrue($user->isResident());
        $this->assertTrue($user->is_active);
    }

    public function test_update_profile_validates_fields(): void
    {
        $this->actingAsRole(RoleName::RESIDENTE);

        $this->putJson('/api/v1/me', [
            'name' => str_repeat('a', 256),
            'phone' => str_repeat('1', 21),
            'zone_id' => 999999,
        ])->assertUnprocessable()->assertJsonValidationErrors(['name', 'phone', 'zone_id']);
    }

    public function test_middleware_revokes_tokens_of_inactive_accounts(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;
        $user->update(['is_active' => false]);

        $this->withToken($token)->getJson('/api/v1/me')->assertForbidden();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_role_middleware_accepts_only_the_configured_roles(): void
    {
        Route::middleware(['auth:sanctum', 'role:moderador,administrador'])
            ->get('/api/v1/test-management', fn () => response()->json(['ok' => true]));

        $this->getJson('/api/v1/test-management')->assertUnauthorized();

        $this->actingAsRole(RoleName::RESIDENTE);
        $this->getJson('/api/v1/test-management')->assertForbidden();

        $this->actingAsRole(RoleName::MODERADOR);
        $this->getJson('/api/v1/test-management')->assertOk();

        $this->actingAsRole(RoleName::ADMINISTRADOR);
        $this->getJson('/api/v1/test-management')->assertOk();
    }

    public function test_login_is_limited_to_five_attempts_per_ip_and_email(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'rate-limit@ambiente.local',
                'password' => 'errada',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'rate-limit@ambiente.local',
            'password' => 'errada',
        ])->assertStatus(429);
    }

    public function test_password_reset_reuses_the_framework_broker_and_revokes_tokens(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'reset@ambiente.local']);
        $user->createToken('api');

        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk();

        Notification::assertSentTo($user, ResetPasswordNotification::class);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => Password::broker()->createToken($user),
            'password' => 'nova-password123',
            'password_confirmation' => 'nova-password123',
        ])->assertOk();

        $this->assertTrue(Hash::check('nova-password123', $user->fresh()->password));
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_email_verification_notification_and_signed_link(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->withRole(RoleName::RESIDENTE)->create();
        $this->actingAs($user, 'sanctum');

        $this->postJson('/api/v1/auth/email/verification-notification')->assertStatus(202);

        Notification::assertSentTo($user, VerifyEmail::class);

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->getKey(),
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        $this->getJson($url)->assertOk();

        $this->assertNotNull($user->fresh()->email_verified_at);
    }
}
