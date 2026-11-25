<?php

namespace App\Support;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class ApiErrorResponder
{
    public function __invoke(Response $response, Throwable $exception, Request $request): Response
    {
        if (! $request->is('api/*') && ! $request->expectsJson()) {
            return $response;
        }

        $payload = $this->payload($response);
        $status = $response->getStatusCode();

        $body = [
            'status' => $status,
            'code' => $this->code($status),
            'message' => $this->message($status, $payload, $exception),
        ];

        if (isset($payload['errors']) && is_array($payload['errors']) && $payload['errors'] !== []) {
            $body['errors'] = $payload['errors'];
        }

        $response->setContent(json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    private function payload(Response $response): array
    {
        $content = $response->getContent();

        if (! is_string($content) || $content === '') {
            return [];
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function code(int $status): string
    {
        return match (true) {
            $status === 401 => 'unauthenticated',
            $status === 403 => 'forbidden',
            $status === 404 => 'not_found',
            $status === 405 => 'method_not_allowed',
            $status === 409 => 'conflict',
            $status === 419 => 'token_mismatch',
            $status === 422 => 'validation_error',
            $status === 429 => 'too_many_requests',
            $status >= 500 => 'server_error',
            default => 'error',
        };
    }

    private function message(int $status, array $payload, Throwable $exception): string
    {
        $messages = [
            401 => 'Autenticação necessária.',
            403 => 'Sem permissão para executar esta acção.',
            404 => 'Recurso não encontrado.',
            405 => 'Método não permitido.',
            409 => 'Conflito com o estado actual do recurso.',
            419 => 'Sessão expirada.',
            422 => 'Os dados fornecidos são inválidos.',
            429 => 'Demasiados pedidos. Tente novamente mais tarde.',
        ];

        if ($status >= 500) {
            return 'Erro interno do servidor.';
        }

        if (isset($messages[$status])) {
            return $messages[$status];
        }

        if (isset($payload['message']) && is_string($payload['message']) && $payload['message'] !== '') {
            return $payload['message'];
        }

        return Response::$statusTexts[$status] ?? ($exception->getMessage() ?: 'Erro na requisição.');
    }
}
