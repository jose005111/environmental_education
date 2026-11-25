<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityParticipation;
use App\Models\Content;
use App\Models\Event;
use App\Models\Quiz;
use App\Models\Report;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public static function permissionMatrix(): iterable
    {
        yield 'visitante lê conteúdos publicados' => ['visitante', 'viewAny', 'class-content', true];
        yield 'residente lê conteúdos publicados' => ['residente', 'viewAny', 'class-content', true];
        yield 'moderador lê conteúdos publicados' => ['moderador', 'viewAny', 'class-content', true];
        yield 'administrador lê conteúdos publicados' => ['administrador', 'viewAny', 'class-content', true];
        yield 'visitante vê conteúdo publicado' => ['visitante', 'view', 'content', true];
        yield 'visitante não vê rascunho' => ['visitante', 'view', 'content-draft', false];
        yield 'residente não vê rascunho' => ['residente', 'view', 'content-draft', false];
        yield 'moderador vê rascunho' => ['moderador', 'view', 'content-draft', true];
        yield 'administrador vê rascunho' => ['administrador', 'view', 'content-draft', true];
        yield 'residente não cria conteúdo' => ['residente', 'create', 'class-content', false];
        yield 'moderador cria conteúdo' => ['moderador', 'create', 'class-content', true];
        yield 'administrador cria conteúdo' => ['administrador', 'create', 'class-content', true];
        yield 'residente não edita conteúdo' => ['residente', 'update', 'content', false];
        yield 'moderador edita conteúdo' => ['moderador', 'update', 'content', true];
        yield 'administrador apaga conteúdo' => ['administrador', 'delete', 'content', true];

        yield 'visitante não lista quizzes' => ['visitante', 'viewAny', 'class-quiz', false];
        yield 'residente lista quizzes' => ['residente', 'viewAny', 'class-quiz', true];
        yield 'moderador lista quizzes' => ['moderador', 'viewAny', 'class-quiz', true];
        yield 'administrador lista quizzes' => ['administrador', 'viewAny', 'class-quiz', true];
        yield 'visitante não vê quiz' => ['visitante', 'view', 'quiz', false];
        yield 'residente vê quiz publicado' => ['residente', 'view', 'quiz', true];
        yield 'residente resolve quiz' => ['residente', 'attempt', 'quiz', true];
        yield 'residente não cria quiz' => ['residente', 'create', 'class-quiz', false];
        yield 'moderador cria quiz' => ['moderador', 'create', 'class-quiz', true];
        yield 'moderador edita quiz' => ['moderador', 'update', 'quiz', true];
        yield 'administrador apaga quiz' => ['administrador', 'delete', 'quiz', true];

        yield 'visitante lista actividades' => ['visitante', 'viewAny', 'class-activity', true];
        yield 'residente lista actividades' => ['residente', 'viewAny', 'class-activity', true];
        yield 'visitante vê actividade aberta' => ['visitante', 'view', 'activity', true];
        yield 'residente não vê rascunho de actividade' => ['residente', 'view', 'activity-draft', false];
        yield 'moderador vê rascunho de actividade' => ['moderador', 'view', 'activity-draft', true];
        yield 'residente inscreve-se' => ['residente', 'join', 'activity', true];
        yield 'moderador não se inscreve' => ['moderador', 'join', 'activity', false];
        yield 'administrador inscreve-se' => ['administrador', 'join', 'activity', true];
        yield 'residente submete a própria prova' => ['residente', 'submit', 'participation-enrolled', true];
        yield 'residente não submete prova alheia' => ['residente', 'submit', 'participation-other', false];
        yield 'moderador revê prova alheia' => ['moderador', 'review', 'participation-other', true];
        yield 'moderador não revê a própria prova' => ['moderador', 'review', 'participation-own', false];
        yield 'residente não revê provas' => ['residente', 'review', 'participation-other', false];
        yield 'administrador revê prova' => ['administrador', 'review', 'participation-other', true];

        yield 'visitante vê lista pública' => ['visitante', 'viewPublic', 'class-report', true];
        yield 'visitante vê caso público' => ['visitante', 'view', 'report-public', true];
        yield 'visitante não vê caso alheio' => ['visitante', 'view', 'report-other', false];
        yield 'residente vê o próprio reporte' => ['residente', 'view', 'report-own', true];
        yield 'residente não vê reporte alheio' => ['residente', 'view', 'report-other', false];
        yield 'moderador vê todos os reportes' => ['moderador', 'view', 'report-other', true];
        yield 'administrador vê todos os reportes' => ['administrador', 'view', 'report-other', true];
        yield 'residente cria reporte' => ['residente', 'create', 'class-report', true];
        yield 'moderador não cria reporte' => ['moderador', 'create', 'class-report', false];
        yield 'administrador cria reporte' => ['administrador', 'create', 'class-report', true];
        yield 'residente não muda estado' => ['residente', 'changeStatus', 'report-own', false];
        yield 'moderador muda estado' => ['moderador', 'changeStatus', 'report-other', true];
        yield 'administrador muda estado' => ['administrador', 'changeStatus', 'report-other', true];
        yield 'moderador atribui responsável' => ['moderador', 'assign', 'report-other', true];
        yield 'residente não atribui responsável' => ['residente', 'assign', 'report-own', false];

        yield 'visitante lista eventos' => ['visitante', 'viewAny', 'class-event', true];
        yield 'residente lista eventos' => ['residente', 'viewAny', 'class-event', true];
        yield 'visitante vê evento publicado' => ['visitante', 'view', 'event', true];
        yield 'residente não vê rascunho de evento' => ['residente', 'view', 'event-draft', false];
        yield 'residente inscreve-se no evento' => ['residente', 'register', 'event', true];
        yield 'moderador não se inscreve no evento' => ['moderador', 'register', 'event', false];
        yield 'administrador inscreve-se no evento' => ['administrador', 'register', 'event', true];
        yield 'residente não se inscreve em rascunho' => ['residente', 'register', 'event-draft', false];
        yield 'moderador cria evento' => ['moderador', 'create', 'class-event', true];
        yield 'residente não cria evento' => ['residente', 'create', 'class-event', false];
        yield 'administrador edita evento' => ['administrador', 'update', 'event', true];
        yield 'moderador vê inscritos' => ['moderador', 'viewRegistrations', 'event', true];
        yield 'residente não vê inscritos' => ['residente', 'viewRegistrations', 'event', false];

        yield 'visitante não lista utilizadores' => ['visitante', 'viewAny', 'class-user', false];
        yield 'residente não lista utilizadores' => ['residente', 'viewAny', 'class-user', false];
        yield 'moderador lista utilizadores' => ['moderador', 'viewAny', 'class-user', true];
        yield 'administrador lista utilizadores' => ['administrador', 'viewAny', 'class-user', true];
        yield 'residente vê o próprio perfil' => ['residente', 'view', 'user-own', true];
        yield 'residente não vê outro perfil' => ['residente', 'view', 'user-other', false];
        yield 'moderador vê outro perfil' => ['moderador', 'view', 'user-other', true];
        yield 'residente edita o próprio perfil' => ['residente', 'update', 'user-own', true];
        yield 'residente não edita outro perfil' => ['residente', 'update', 'user-other', false];
        yield 'administrador gere utilizadores' => ['administrador', 'manage', 'user-other', true];
        yield 'moderador não gere utilizadores' => ['moderador', 'manage', 'user-other', false];
        yield 'administrador muda papéis' => ['administrador', 'changeRole', 'user-other', true];
        yield 'administrador desactiva contas' => ['administrador', 'toggleActive', 'user-other', true];
    }

    #[DataProvider('permissionMatrix')]
    public function test_permission_matrix(string $role, string $ability, string $targetKey, bool $allowed): void
    {
        $user = $this->userForRole($role);
        $target = $this->target($targetKey, $user);
        $gate = Gate::forUser($user);

        $this->assertSame($allowed, $gate->allows($ability, $target), "{$role}: {$ability} em {$targetKey}");
    }

    private function userForRole(string $role): ?User
    {
        return match ($role) {
            'visitante' => null,
            'residente' => User::factory()->create(),
            'moderador' => User::factory()->moderator()->create(),
            'administrador' => User::factory()->admin()->create(),
            default => throw new \InvalidArgumentException("Papel desconhecido: {$role}"),
        };
    }

    private function target(string $key, ?User $user): object|string
    {
        if (str_starts_with($key, 'class-')) {
            return match ($key) {
                'class-content' => Content::class,
                'class-quiz' => Quiz::class,
                'class-activity' => Activity::class,
                'class-report' => Report::class,
                'class-event' => Event::class,
                'class-user' => User::class,
                default => throw new \InvalidArgumentException("Alvo desconhecido: {$key}"),
            };
        }

        if (str_ends_with($key, '-own') && $user === null) {
            throw new \InvalidArgumentException("{$key} exige um utilizador autenticado");
        }

        return match ($key) {
            'content' => Content::factory()->published()->create(),
            'content-draft' => Content::factory()->create(),
            'quiz' => Quiz::factory()->published()->create(),
            'activity' => Activity::factory()->open()->create(),
            'activity-draft' => Activity::factory()->create(),
            'participation-enrolled' => ActivityParticipation::factory()->for($user)->create(),
            'participation-own' => ActivityParticipation::factory()->for($user)->submitted()->create(),
            'participation-other' => ActivityParticipation::factory()->submitted()->create(),
            'report-public' => Report::factory()->resolved()->create(),
            'report-other' => Report::factory()->create(),
            'report-own' => Report::factory()->for($user)->create(),
            'event' => Event::factory()->published()->create(),
            'event-draft' => Event::factory()->create(),
            'user-own' => $user,
            'user-other' => User::factory()->create(),
            default => throw new \InvalidArgumentException("Alvo desconhecido: {$key}"),
        };
    }
}
