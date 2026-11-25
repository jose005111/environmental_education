<?php

namespace App\Support;

use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;

class ApiDocumentation
{
    private const PUBLIC_OPERATIONS = [
        'v1.public.home',
        'v1.auth.register',
        'v1.auth.login',
        'v1.auth.forgot-password',
        'v1.auth.reset-password',
        'verification.verify',
        'v1.categories.index',
        'v1.categories.show',
        'v1.zones.index',
        'v1.contents.index',
        'v1.contents.show',
        'v1.activities.index',
        'v1.activities.show',
        'v1.events.index',
        'v1.events.show',
        'v1.reports.public',
        'v1.reports.show',
    ];

    private const CONFLICT_OPERATIONS = [
        'v1.activities.join',
        'v1.activities.submit',
        'v1.activities.participations.review',
        'v1.events.register',
        'v1.events.cancel-registration',
        'v1.events.mark-present',
        'v1.quizzes.attempts.store',
        'v1.quizzes.update',
        'v1.quizzes.destroy',
        'v1.categories.destroy',
        'v1.zones.destroy',
    ];

    private const REQUEST_EXAMPLES = [
        'v1.auth.register' => [
            'name' => 'Maria Silva',
            'email' => 'maria@example.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'zone_id' => 1,
        ],
        'v1.auth.login' => [
            'email' => 'maria@example.test',
            'password' => 'Password123!',
            'device_name' => 'mobile',
        ],
        'v1.auth.forgot-password' => ['email' => 'maria@example.test'],
        'v1.auth.reset-password' => [
            'email' => 'maria@example.test',
            'token' => 'token-recebido-por-email',
            'password' => 'NovaPassword123!',
            'password_confirmation' => 'NovaPassword123!',
        ],
        'v1.me.update' => ['name' => 'Maria Silva', 'phone' => '(21) 98888-7777'],
        'v1.contents.store' => [
            'title' => 'Separar resíduos em casa',
            'summary' => 'Guia rápido para separação de resíduos.',
            'body' => 'Conteúdo completo...',
            'type' => 'artigo',
            'category_id' => 1,
        ],
        'v1.reports.store' => [
            'title' => 'Lixo acumulado na praça',
            'description' => 'Acumulação junto ao banco.',
            'category_id' => 1,
            'zone_id' => 1,
            'is_public' => true,
        ],
        'v1.reports.assign' => ['assigned_to' => 2],
        'v1.reports.status' => ['status' => 'em_analise'],
        'v1.activities.store' => [
            'title' => 'Oficina de compostagem',
            'summary' => 'Aprender a compostar resíduos.',
            'instructions' => 'Trazer luvas.',
            'category_id' => 1,
            'status' => 'aberta',
        ],
        'v1.events.store' => [
            'title' => 'Feira de reciclagem',
            'description' => 'Feira comunitária.',
            'location' => 'Praça Central',
            'starts_at' => '2026-12-10T09:00:00Z',
            'ends_at' => '2026-12-10T12:00:00Z',
            'category_id' => 1,
            'zone_id' => 1,
        ],
        'v1.quizzes.store' => [
            'title' => 'Quiz de reciclagem',
            'description' => 'Perguntas sobre separação de resíduos.',
            'category_id' => 1,
            'status' => 'publicado',
            'questions' => [[
                'statement' => 'Onde vai o papel?',
                'options' => [
                    ['text' => 'Lixo comum', 'is_correct' => false, 'position' => 1],
                    ['text' => 'Reciclagem', 'is_correct' => true, 'position' => 2],
                ],
            ]],
        ],
        'v1.users.update' => ['role_id' => 3, 'is_active' => false],
    ];

    private const ERROR_MESSAGES = [
        401 => 'Autenticação necessária.',
        403 => 'Sem permissão para executar esta acção.',
        404 => 'Recurso não encontrado.',
        409 => 'Conflito com o estado actual do recurso.',
        422 => 'Os dados fornecidos são inválidos.',
        429 => 'Demasiados pedidos. Tente novamente mais tarde.',
    ];

    private const ERROR_CODES = [
        401 => 'unauthenticated',
        403 => 'forbidden',
        404 => 'not_found',
        409 => 'conflict',
        422 => 'validation_error',
        429 => 'too_many_requests',
    ];

    public static function extend(OpenApi $openApi): void
    {
        foreach ($openApi->paths as $path) {
            foreach ($path->operations as $operation) {
                self::describe($operation, $path->path);
            }
        }
    }

    private static function describe(Operation $operation, string $path): void
    {
        $operationId = (string) $operation->operationId;
        $isPublic = in_array($operationId, self::PUBLIC_OPERATIONS, true);

        $lines = [$operation->description];
        $lines[] = 'Papel exigido: '.self::role($operationId).'.';

        if ($isPublic) {
            $lines[] = 'Endpoint público; não requer token.';
        } else {
            $lines[] = 'Envie `Authorization: Bearer <token>`.';
        }

        if (isset(self::REQUEST_EXAMPLES[$operationId])) {
            $lines[] = 'Exemplo de pedido: '.self::json(self::REQUEST_EXAMPLES[$operationId]);
        }

        $lines[] = 'Exemplo de resposta de erro: `{"status":422,"code":"validation_error","message":"Os dados fornecidos são inválidos.","errors":{}}`.';

        $operation->description = trim(implode("\n\n", array_filter($lines, fn (string $line) => $line !== '')));

        if (! $isPublic) {
            self::addError($operation, 401);
            self::addError($operation, 403);
            self::addError($operation, 422);
        }

        if (str_contains($path, '{')) {
            self::addError($operation, 404);
        }

        if (in_array($operationId, self::CONFLICT_OPERATIONS, true)) {
            self::addError($operation, 409);
        }

        self::addError($operation, 429);
    }

    private static function role(string $operationId): string
    {
        if (in_array($operationId, self::PUBLIC_OPERATIONS, true)) {
            return $operationId === 'v1.reports.show'
                ? 'visitante (reporte público), proprietário ou moderador/administrador'
                : 'visitante';
        }

        return match (true) {
            str_starts_with($operationId, 'v1.admin.site.') => 'administrador',
            str_starts_with($operationId, 'v1.users.') => 'administrador',
            str_starts_with($operationId, 'v1.zones.') && $operationId !== 'v1.zones.index' => 'administrador',
            str_starts_with($operationId, 'v1.categories.') && ! in_array($operationId, ['v1.categories.index', 'v1.categories.show'], true) => 'administrador',
            str_starts_with($operationId, 'v1.activities.participations.') => 'moderador ou administrador',
            in_array($operationId, ['v1.activities.store', 'v1.activities.update', 'v1.activities.destroy'], true) => 'moderador ou administrador',
            in_array($operationId, ['v1.activities.join'], true) => 'residente ou administrador',
            in_array($operationId, ['v1.activities.submit'], true) => 'residente responsável pela participação',
            in_array($operationId, ['v1.contents.store', 'v1.contents.update', 'v1.contents.publish', 'v1.contents.destroy'], true) => 'moderador ou administrador',
            in_array($operationId, ['v1.events.store', 'v1.events.update', 'v1.events.destroy', 'v1.events.registrations'], true) => 'moderador ou administrador',
            in_array($operationId, ['v1.events.register', 'v1.events.cancel-registration'], true) => 'residente ou administrador',
            in_array($operationId, ['v1.quizzes.store', 'v1.quizzes.update', 'v1.quizzes.destroy'], true) => 'moderador ou administrador',
            in_array($operationId, ['v1.quizzes.attempts.store'], true) => 'residente ou administrador',
            in_array($operationId, ['v1.reports.index', 'v1.reports.assign', 'v1.reports.status'], true) => 'moderador ou administrador',
            in_array($operationId, ['v1.reports.store'], true) => 'residente ou administrador',
            str_starts_with($operationId, 'v1.quizzes.') => 'residente (leitura), moderador ou administrador',
            default => 'qualquer utilizador autenticado',
        };
    }

    private static function addError(Operation $operation, int $code): void
    {
        foreach ($operation->responses ?? [] as $response) {
            if ($response instanceof Response && $response->code === $code) {
                if ($response->description === '') {
                    $response->setDescription(self::ERROR_MESSAGES[$code]);
                }

                if ($response->content === []) {
                    $response->setContent('application/json', self::errorSchema($code));
                }

                return;
            }
        }

        $operation->addResponse(
            Response::make($code)
                ->setDescription(self::ERROR_MESSAGES[$code])
                ->setContent('application/json', self::errorSchema($code)),
        );
    }

    private static function errorSchema(int $code): Schema
    {
        $example = [
            'status' => $code,
            'code' => self::ERROR_CODES[$code],
            'message' => self::ERROR_MESSAGES[$code],
        ];

        if ($code === 422) {
            $example['errors'] = ['campo' => ['Mensagem de validação.']];
        }

        return Schema::fromType((new ObjectType)->example($example));
    }

    private static function json(array $data): string
    {
        return '`'.json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).'`';
    }
}
