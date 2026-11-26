<h1>PLATAFORMA DIGITAL PARA EDUCAÇÃO AMBIENTAL EM COMUNIDADES</h1>

API REST em Laravel para gestão de conteúdos educativos, quizzes, atividades, eventos e reportes ambientais comunitários. A autenticação usa Laravel Sanctum (`Bearer token`) e o acesso é controlado pelos papéis `residente`, `moderador` e `administrador`.

## Requisitos

- PHP 8.5+
- Composer 2+
- MySQL 8+ (os testes usam SQLite em memória)
- Extensão PCOV apenas para gerar cobertura

## Instalação

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Configure `DB_*` no `.env` e execute:

```bash
php artisan migrate --seed
php artisan serve
```

A API fica disponível em `http://127.0.0.1:8000/api/v1`.

## Contas de demonstração

O seeder cria usuários verificados com a senha `password`:

| E-mail | Papel |
| --- | --- |
| `admin@ambiente.local` | administrador |
| `moderador@ambiente.local` | moderador |
| `joao@ambiente.local` | residente |
| `ana@ambiente.local` | residente |

O e-mail/senha do administrador podem ser alterados com `ADMIN_EMAIL`, `ADMIN_NAME` e `ADMIN_PASSWORD` no `.env`.

## Autenticação

```bash
curl -X POST http://127.0.0.1:8000/api/v1/auth/login \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -d '{"email":"joao@ambiente.local","password":"password","device_name":"cli"}'
```

Envie o token retornado nas rotas protegidas:

```text
Authorization: Bearer <token>
```

Registros novos precisam verificar o e-mail pela URL assinada enviada ao usuário. O endpoint `POST /api/v1/auth/email/verification-notification` reenvia a notificação para usuários autenticados.

## Documentação

- OpenAPI (JSON): `GET /docs/api.json`
- Interface Swagger/Stoplight: `GET /docs/api`

Em `local`, a documentação é pública. Em outros ambientes, exige token de administrador. O documento descreve papéis por endpoint, exemplos de requisição, esquemas de resposta e erros 401, 403, 404, 409, 422 e 429.

Uma coleção pronta para importar está em `postman/ambiente-api.postman_collection.json`. Ela possui 60 requisições agrupadas por módulo; ao executar `Auth > Login`, o token é salvo automaticamente na variável `token` da coleção.

Para regenerar a coleção a partir do OpenAPI atual:

```bash
php artisan scramble:export --stdout > openapi.json
python3 postman/generate.py openapi.json
```

## Convenções da API

- Prefixo: `/api/v1`.
- Respostas de sucesso seguem o formato `{ "message": "...", "data": ... }`; listagens incluem `meta` de paginação.
- Erros seguem `{ "status", "code", "message", "errors" }`.
- Parâmetros de listagem: `page`, `per_page` (máximo 50), `sort` (use `-` para descendente), `search` e `filter[campo]`.
- Filtros e ordenações não suportados retornam 422.
- Uploads usam `multipart/form-data`; tamanho máximo configurável em `UPLOAD_MAX_SIZE_KB`.

## Página inicial pública

`GET /api/v1/public/home` devolve numa chamada os blocos da página inicial: `hero`, `stats`, `categories`, `featured_contents`, `upcoming_events`, `open_activities`, `resolved_reports`, `tips`, `how_it_works` e `settings`. Esta resposta segue directamente o contrato da página inicial, sem o envelope `data` das outras rotas. O endpoint é público.

O administrador consulta e actualiza os blocos editáveis por `GET /api/v1/admin/site` e `PUT /api/v1/admin/site`, usando um token de administrador. A configuração começa vazia e é criada na primeira actualização. Execute as migrations para criar `site_settings` antes de usar estes endpoints.

Moderadores e administradores podem pesquisar moradores por `GET /api/v1/residents?search=termo`, consultar `GET /api/v1/residents/{id}` e obter o histórico paginado em `GET /api/v1/residents/{id}/records?type=quizzes|activities|events|reports`. O detalhe de uma actividade devolve `my_participation_status` e o de um evento devolve `my_registration_status` para a conta autenticada, permitindo apresentar o estado da inscrição no cliente. No detalhe de quizzes, a explicação e a opção correcta são devolvidas apenas a moderadores e administradores.

## Variáveis de ambiente relevantes

| Variável | Padrão | Finalidade |
| --- | --- | --- |
| `APP_DEBUG` | `false` | Detalhes de erro; nunca habilite em produção |
| `FORCE_HTTPS` | `false` | Força esquema HTTPS nas URLs geradas |
| `TRUSTED_PROXIES` | `127.0.0.1,::1` | Proxies confiáveis para headers `X-Forwarded-*` |
| `SANCTUM_TOKEN_EXPIRATION` | `10080` | Validade do token em minutos |
| `LOG_STACK` | `daily` | Rotação diária de logs |
| `CACHE_STORE` | `database` | Store usado pelo cache da aplicação |
| `QUEUE_CONNECTION` | `database` | Fila para jobs assíncronos |
| `FILESYSTEM_DISK` | `local` | Armazenamento padrão de arquivos |
| `UPLOAD_DISK` | `public` | Disco usado por fotos e comprovantes |
| `UPLOAD_MAX_SIZE_KB` | `5120` | Tamanho máximo por upload |
| `UPLOAD_THUMBNAIL_MAX_DIMENSION` | `1200` | Lado máximo da miniatura WebP gerada em fila |
| `REPORT_PHOTO_LIMIT` | `5` | Fotografias por reporte |

## Testes e qualidade

```bash
composer test
composer lint
composer analyse
composer coverage
```

`composer coverage` habilita PCOV apenas para a execução e gera o relatório em `build/coverage`. A meta de cobertura é 80% em `app/Actions` e `app/Policies`.

## Comandos úteis

```bash
php artisan scramble:clear
php artisan scramble:export --stdout
php artisan route:list --path=api/v1 --except-vendor
php artisan queue:work
php artisan schedule:work
```
