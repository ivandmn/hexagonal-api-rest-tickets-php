# Ticket API

A REST API for managing tasks (tickets) and the users they are assigned to, built with **hexagonal architecture**, **DDD** and **CQRS** on **PHP 8.5**, **Symfony 7.4**, **Doctrine ORM 3** and **PostgreSQL 18**, served by **FrankenPHP** in worker mode.

The boundaries are not just a convention: deptrac enforces the layer rules on every CI run, PHPStan runs at level 10 with no baseline, and every error the API returns follows [RFC 9457 Problem Details](https://www.rfc-editor.org/rfc/rfc9457) with stable, documented codes.

---

## Contents

- [Features](#features)
- [Quick start](#quick-start)
- [API](#api)
- [Error handling](#error-handling)
- [Domain rules](#domain-rules)
- [Architecture](#architecture)
- [Testing](#testing)
- [Code quality and CI](#code-quality-and-ci)
- [Debugging](#debugging)
- [Deployment (Dokploy)](#deployment-dokploy)
- [Make commands](#make-commands)
- [Roadmap](#roadmap)

---

## Features

- **Users:** create, read, list, partially update and delete. A user cannot be deleted while any of their tasks is unfinished; once deleted, their completed tasks go with them.
- **Tasks:** create, read, list (filtered and paginated), partially update, assign to a user, unassign and delete.
- **Domain events:** `TaskAssigned`, `TaskCompleted` and `UserDeleted`, handled synchronously (for now they are logged).
- **A predictable contract:**
  - `snake_case` everywhere;
  - `201 Created` + `Location`, `204 No Content`, `PATCH` for partial updates;
  - a single success envelope and Problem Details for every error.
- **Interactive documentation** generated from the code (OpenAPI 3 + Scalar).
- **A debug page** (`/info`) showing PHP, extensions, OPcache, FrankenPHP, Symfony and Doctrine, available locally only.

---

## Quick start

### Requirements

- Docker with Compose v2
- GNU Make

The dev stack only runs the app. PostgreSQL (`app-postgres`), the test PostgreSQL (`app-postgres-test`) and Redis (`app-redis`) come from the `infrastructure` repository (`docker-postgres`, `docker-postgres-test`, `docker-redis`): start them there first, on the same `EXTERNAL_NET` network.

### 1. Clone and configure

```bash
git clone <repository-url> hexagonal-api-rest-tickets-php
cd hexagonal-api-rest-tickets-php

cp .env.example .env              # app settings: DATABASE_URL, APP_SECRET...
cp .env.local.example .env.local  # your machine: USER/USER_ID/GROUP_ID, Xdebug, ports (not committed)
```

`.env.example` already points at `app-postgres` and `app-redis`: fill in their passwords. The app owns a single PostgreSQL schema, `tickets`, and ignores everything else in that database.

Git over SSH inside the container uses your SSH agent, not your `~/.ssh`: start it on the host (`eval "$(ssh-agent)" && ssh-add`) before `make up`.

### 2. Start

```bash
docker network create app   # once: the stack joins this external network
make build                  # build the dev image
make up                     # start the app (databases and Redis: infrastructure repo)
make install                # composer install
make db-migrate             # create the "tickets" schema and run the migrations
```

### 3. Open

| What | URL |
|------|-----|
| API | <http://localhost:8080/api/v1> |
| API docs (Scalar UI) | <http://localhost:8080/api/docs> |
| OpenAPI spec (JSON) | <http://localhost:8080/api/docs.json> |
| Health check | <http://localhost:8080/health> |
| Debug info (local/dev only) | <http://localhost:8080/info> (`?format=json`, `?sections=php,doctrine`) |

The port comes from `HTTP_WEB_PORT` in `.env.local` (default `8080`).

---

## API

### Conventions

| Topic | Rule |
|-------|------|
| Versioning | Every endpoint lives under `/api/v1`. The prefix is set once in `config/routes.yaml` (controllers declare `/tasks`, `/users`...); a breaking change goes into a new version with its own prefix. |
| Naming | Every JSON key is `snake_case`, in requests, responses and error fields. Unknown keys (including camelCase ones like `dueDate`) are rejected with a 422. |
| Requests | Bodies are JSON objects (`Content-Type: application/json`). |
| Success | `{"status": "success", "data": {...}}`. Collections are `{"status": "success", "data": [...], "meta": {...}}`, and an empty list is still `200` with `"data": []`. |
| Create | `201 Created`, the new resource in `data` and its URL in the `Location` header. |
| Delete | `204 No Content`, empty body. |
| Update | `PATCH`: only the fields you send change. To remove the due date, send `"due_date": null`; to keep it, leave the key out. |
| IDs | UUID v4. A path id that is not a UUID gets a 404. |
| Dates | RFC 3339 with an explicit offset (`2026-12-31T18:00:00+02:00`, milliseconds optional). Always returned in UTC (`2026-12-31T16:00:00+00:00`). Impossible dates such as `2026-02-30` are rejected instead of rolling over. |
| Errors | `application/problem+json`; see [Error handling](#error-handling). |

### Endpoints

| Method | Path | Description | Success |
|--------|------|-------------|---------|
| `GET` | `/api/v1/tasks` | List tasks, newest first. Filters: `status`, `priority`, `assigned_to` (user id), `due_from` / `due_to` (RFC 3339, inclusive; tasks without a due date are left out). Pagination: `page` (default 1), `limit` (1–100, default 50). | 200 |
| `POST` | `/api/v1/tasks` | Create a task. | 201 |
| `GET` | `/api/v1/tasks/{id}` | Task details. | 200 |
| `PATCH` | `/api/v1/tasks/{id}` | Partial update: `title`, `description`, `status`, `priority`, `due_date`. | 200 |
| `PATCH` | `/api/v1/tasks/{id}/assign` | Assign the task to a user (`user_id`). Returns the task. | 200 |
| `DELETE` | `/api/v1/tasks/{id}/assign` | Unassign the task (a no-op if nobody has it). Returns the task. | 200 |
| `DELETE` | `/api/v1/tasks/{id}` | Delete a task (pending tasks only). | 204 |
| `GET` | `/api/v1/users` | List users, oldest first. | 200 |
| `POST` | `/api/v1/users` | Create a user. | 201 |
| `GET` | `/api/v1/users/{id}` | User details. | 200 |
| `PATCH` | `/api/v1/users/{id}` | Partial update: `email`, `name`. | 200 |
| `DELETE` | `/api/v1/users/{id}` | Delete a user (none of their tasks may be unfinished). | 204 |

The documentation at `/api/docs` lists every request and response schema, constraint and error example.

### Examples

**Create a task**

```http
POST /api/v1/tasks
Content-Type: application/json

{
  "title": "Fix login redirect",
  "description": "Users are redirected to a 404 after logging in",
  "priority": "high",
  "due_date": "2026-12-31T18:00:00+00:00"
}
```

```http
HTTP/1.1 201 Created
Location: http://localhost:8080/api/v1/tasks/3f1c2a9e-8b4d-4c6e-9a7f-2d5b8e1c4a63
Content-Type: application/json

{
  "status": "success",
  "data": {
    "id": "3f1c2a9e-8b4d-4c6e-9a7f-2d5b8e1c4a63",
    "title": "Fix login redirect",
    "description": "Users are redirected to a 404 after logging in",
    "status": "pending",
    "priority": "high",
    "assigned_to": null,
    "due_date": "2026-12-31T18:00:00+00:00",
    "created_at": "2026-10-11T08:00:00+00:00",
    "updated_at": null
  }
}
```

**List tasks**

```http
GET /api/v1/tasks?status=pending&priority=high&page=1&limit=20
```

```json
{
  "status": "success",
  "data": [
    {
      "id": "3f1c2a9e-8b4d-4c6e-9a7f-2d5b8e1c4a63",
      "title": "Fix login redirect",
      "description": "Users are redirected to a 404 after logging in",
      "status": "pending",
      "priority": "high",
      "assigned_to": { "id": "b7e4d2c1-5a3f-4e8b-8c9d-6f1a2b3c4d5e", "name": "Jane Doe" },
      "due_date": "2026-12-31T18:00:00+00:00",
      "created_at": "2026-10-11T08:00:00+00:00",
      "updated_at": "2026-10-11T09:30:00+00:00"
    }
  ],
  "meta": { "total": 1, "page": 1, "limit": 20 }
}
```

`meta.total` counts every task that matches the filters, across all pages. The users list returns `meta: {"total": N}`.

---

## Error handling

Every error under `/api` is a Problem Details document (`application/problem+json`). A single class builds them all: `App\UI\Http\Exception\ApiExceptionListener`. Controllers never catch exceptions.

```json
{
  "type": "VALIDATION_ERROR",
  "title": "The submitted data is not valid.",
  "status": 422,
  "errors": [
    { "field": "title", "code": "VALUE_TOO_SHORT", "message": "This value must be at least 5 characters long.", "params": { "min": 5 } },
    { "field": "due_date", "code": "VALUE_INVALID_FORMAT", "message": "Invalid format, expected RFC3339 date-time (e.g. 2026-12-31T18:00:00+00:00).", "params": { "expected": "RFC3339 date-time (e.g. 2026-12-31T18:00:00+00:00)" } }
  ],
  "trace_id": "a3f9c2e17b4d8e05"
}
```

- **Branch on `type` and `errors[].code`**, never on `title` or `message`: codes are stable, texts may change.
- **Validation lists every invalid field at once**, not just the first one.
- **Business errors carry context in `params`**, for example `TASK_NOT_FOUND` → `{"task_id": "..."}`. When an error belongs to a field, such as `TASK_DUE_DATE_IN_PAST` → `due_date`, it also appears in `errors[]`, so clients can handle it like a validation error.
- **`trace_id` correlates the response with the logs.** It reuses the `X-Request-Id` header when the client sends one, and comes back in that same response header.
- **Unexpected errors** are a generic 500. Details are included only with `APP_DEBUG=1`.

| Status | Meaning | Codes |
|--------|---------|-------|
| 400 | Malformed request | `REQUEST_MALFORMED_JSON`, `REQUEST_EMPTY_BODY`, `REQUEST_INVALID_BODY` |
| 404 | Missing resource or unknown route | `TASK_NOT_FOUND`, `USER_NOT_FOUND`, `NOT_FOUND` |
| 405 | Method not allowed (with an `Allow` header) | `METHOD_NOT_ALLOWED` |
| 409 | State incompatible with the operation | `TASK_STATUS_TRANSITION_NOT_ALLOWED`, `TASK_DELETION_NOT_ALLOWED`, `USER_ALREADY_EXISTS`, `USER_DELETION_NOT_ALLOWED` |
| 415 | Body is not JSON | `UNSUPPORTED_MEDIA_TYPE` |
| 422 | Invalid values or a broken business rule | `VALIDATION_ERROR` (with `VALUE_*` and `REQUEST_UNKNOWN_FIELD` / `REQUEST_INVALID_TYPE` per field), `TASK_DUE_DATE_IN_PAST` |
| 500 | Unexpected error | `INTERNAL_ERROR` |

### How it fits together

- **Domain exceptions** extend `App\Domain\Exception\DomainException`. Each carries a stable `errorCode` and `snake_case` `params`, and knows nothing about HTTP. Value objects throw the generic `InvalidValueException` (`VALUE_*` codes).
- **There are no request DTOs.** `Payload` (`src/UI/Http/Request`) only checks the shape of the input: a JSON object, no unknown fields, and each field present (`VALUE_REQUIRED`) and of the type the controller asks for (`string()`, `int()`, `float()`, `bool()` and their `optional*()` variants; anything else is `REQUEST_INVALID_TYPE`, nothing is cast). Missing and `null` are told apart with `has()`. Every value rule lives in the value object, which the command builds; `Field::of()` tags a failure with the input name, so the error points at the right field. Errors are fail-fast: the first rejected field is reported.
- **`config/packages/error_mapping.yaml`** maps every code to its HTTP status, public message (with `{placeholders}`) and optional field.

### Adding an error

1. Create the exception in `src/Domain/Exception/<Context>/` with `public const ERROR_CODE = 'CONTEXT_SOMETHING'` (UPPER_SNAKE_CASE, never reused).
2. Map it in `config/packages/error_mapping.yaml`.
3. `ErrorCodeCoverageTest` fails until every declared code is mapped, and nothing else is.

---

## Domain rules

| Rule | Where |
|------|-------|
| Title: 5–255 characters; description: up to 1,000. Lengths count characters, not bytes, after trimming. | `TaskTitle`, `TaskDescription` |
| User name: 5–255 characters. Email: valid, at most 255 characters, stored in lower case and unique. | `UserName`, `UserEmail`, unique index |
| New tasks start `pending` and unassigned; priority defaults to `low`. | `Task::create()` |
| Status only moves forward, `pending` → `in_progress` → `completed`, and nothing leaves `completed`. | `TaskStatus::canTransitionTo()` |
| The due date cannot be set in the past. A stored date that has since passed may be kept or cleared. | `Task::create()`, `Task::changeDueDate()`, using the injected PSR-20 clock |
| Only `pending` tasks can be deleted. | `Task::canBeDeleted()` |
| A user can be deleted only when all their tasks are completed. Those tasks are then deleted too (`ON DELETE CASCADE`). | `User::delete()` |
| An email can only be changed to one no other user has. | `UpdateUserCommandHandler`, unique index |
| Re-sending the current values is a no-op: no write, `updated_at` unchanged, no event. | `Task::change*()`, `User::change*()` |
| `created_at` / `updated_at` come from the injected clock, in UTC and whole seconds. | `DateTime` value object |

---

## Architecture

```
            ┌──────────────── UI (inbound adapters) ────────────────┐
HTTP  ───►  │ controllers · Payload · ApiResponder · errors         │
            └───────────────────────────┬───────────────────────────┘
                                        │ commands / queries (CQRS buses)
            ┌───────────────────────────▼───────────────────────────┐
            │ Application: handlers · read models (Views) · ports   │
            └───────────────────────────┬───────────────────────────┘
                                        │
            ┌───────────────────────────▼───────────────────────────┐
            │ Domain: aggregates · value objects · rules · repos    │
            └───────────────────────────▲───────────────────────────┘
                                        │ implements the ports
            ┌───────────────────────────┴───────────────────────────┐
            │ Infrastructure (outbound): Doctrine · Messenger · log │
            └───────────────────────────────────────────────────────┘
```

| Layer | May depend on |
|-------|---------------|
| Domain | `ramsey/uuid`, PSR-20 clock interface |
| Application | Domain, PSR-3, `ramsey/uuid`, PSR-20 |
| Infrastructure | Domain, Application, framework |
| UI | Domain, Application, framework |

UI and Infrastructure never depend on each other. `deptrac.yaml` enforces all of this; the current result is 0 violations and 0 uncovered classes.

### Request lifecycle

1. **A controller** (`src/UI/Http/Controller/Api`, one class per action) reads the primitives with `Payload` and turns them into a command or query.
2. **Commands and queries build their value objects in the constructor** (through `Field::of()`), so invalid input never reaches the bus. Handlers only orchestrate.
3. **Command handlers return the view of the resource they created or changed** (`TaskView`, `UserView`), or nothing when it is gone (delete). For creation, the controller generates the id first.
4. **Query handlers return read models** from `Application/DTO/Views/<Aggregate>` (`TaskView`, `UserView`...), which are plain readonly objects.
5. **`ApiResponder`** serializes them to JSON: `snake_case`, RFC 3339 dates, success envelope.
6. **Any exception** ends in `ApiExceptionListener`, which renders Problem Details.

### Project structure

```
src/
├── Domain/                       # Pure PHP: no framework
│   ├── Model/                    # Task, User aggregates (AggregateRoot records their events)
│   ├── Event/                    # Domain events: Task/ (TaskAssigned, TaskCompleted), User/ (UserDeleted)
│   ├── ValueObject/              # Uuid, Email, DateTime + Task/ and User/ value objects
│   ├── Repository/               # Repository ports, TaskCriteria
│   └── Exception/                # DomainException + Task/ and User/ errors
├── Application/
│   ├── Command/                  # Write side: <Aggregate>/<UseCase>/<UseCase>Command + Handler
│   │   ├── Task/                 #   AssignTaskToUser, CreateTask, DeleteTask, UnassignTask, UpdateTask
│   │   └── User/                 #   CreateUser, DeleteUser, UpdateUser
│   ├── Query/                    # Read side: <Aggregate>/<UseCase>/<UseCase>Query + Handler
│   │   ├── Task/                 #   GetTaskDetails, ListTasks
│   │   └── User/                 #   GetUserDetails, ListUsers
│   ├── DTO/Views/                # Read models returned by queries and commands
│   │   ├── Task/                 #   TaskView, TaskListView, AssignedUserView
│   │   └── User/                 #   UserView, UserListView
│   ├── Validation/               # Field::of(): tags a rejected input with its name
│   └── Event/                    # Event bus port + <Aggregate>/ event handlers (Log*EventHandler)
├── Infrastructure/
│   ├── Bus/Symfony/              # Messenger command, query and event buses
│   ├── Persistence/Doctrine/
│   │   ├── Mapping/              # XML mapping of the domain models (*.orm.xml)
│   │   ├── Type/                 # DBAL types: one per value object (UtcDateTimeType for created/updated)
│   │   ├── Repository/           # Repository adapters
│   │   └── Migration/            # Doctrine migrations
│   └── Symfony/                  # Kernel
└── UI/
    ├── Http/
    │   ├── Controller/           # Api/ (one class per action), Info/, health
    │   ├── Request/              # Payload: request input → primitives
    │   ├── Response/             # ApiResponder (success envelope)
    │   ├── Exception/            # ApiExceptionListener, error code mapping
    │   ├── OpenApi/              # Reusable OpenAPI attributes and schemas
    │   └── Info/                 # /info debug page
    └── Console/                  # CLI entry points

tests/
├── Unit/                         # Domain, Application, Infrastructure, UI (no database)
├── Integration/                  # Doctrine repositories, DBAL types and migrations (test database)
├── Functional/                   # HTTP end to end (test database)
└── Support/                      # Object mother, in-memory repositories, spies, assertions
```

### Persistence

- **No ORM attributes in the domain.** Doctrine maps the aggregates through XML (`Infrastructure/Persistence/Doctrine/Mapping`), and every value object has its own DBAL type, so entities load straight into value objects.
- **Everything lives in the PostgreSQL schema `tickets`**, including the migrations table. `schema_filter` keeps Doctrine away from any other schema in the same database.
- **Columns:** UUID primary keys, `timestamptz` for every date, and a foreign key from `task.user_id` to `users.id` with `ON DELETE CASCADE`.

---

## Testing

| Suite | Scope | Database |
|-------|-------|----------|
| `unit` | Domain rules, value objects, handlers (in-memory repositories and `MockClock`), error mapping | No |
| `integration` | Doctrine repositories against Postgres (every DBAL type, round trips), migrations vs mapping | Yes |
| `functional` | Every endpoint end to end, plus `/health` and `/api/docs.json` | Yes |

```bash
make test             # every suite
make test-unit        # unit only (no database needed)
make test-integration # integration only
make test-functional  # functional only
make test-coverage    # HTML/XML/text report in var/phpunit/coverage
```

**The test database is isolated.** The test environment reads `TEST_DATABASE_URL` (defined in `.env.test`), never `DATABASE_URL`, so the app database cannot be touched by mistake. It points to `app-postgres-test` from the infrastructure repo. Tests use an in-memory cache, never Redis. Before any integration or functional test runs, PHPUnit creates the test database if needed and runs the pending migrations (`tests/Support/PHPUnit/MigrateTestDatabaseExtension.php`); unit-only runs never touch it. Each test runs inside a transaction that is rolled back (`dama/doctrine-test-bundle`).

PHPUnit reads `phpunit.dist.xml`. Copy it to `phpunit.xml` (ignored by git) for local tweaks.

---

## Code quality and CI

| Tool | Command | Standard |
|------|---------|----------|
| PHP-CS-Fixer | `make cs` / `make cs-fix` | Annotated rules in `.php-cs-fixer.dist.php` |
| PHPStan | `make phpstan` | Level 10, no baseline; analyses the `test` container |
| deptrac | `make deptrac` | Layer rules above; fails on uncovered classes |
| All of the above + unit tests | `make code-check` | Fast, no database |
| Everything CI runs | `make ci` | Includes the functional suite |

[GitHub Actions](.github/workflows/ci.yml) runs on every push to `main` and on every pull request:

- **`quality` job:** code style, deptrac and PHPStan.
- **`tests` job:**
  1. starts a `postgres:18` service;
  2. creates the schema and runs the migrations;
  3. validates the mapping against the database;
  4. runs the unit and functional suites.

---

## Debugging

### `/info`

The `/info` page shows a report of the running environment: PHP and its configuration, extensions, OPcache, Xdebug, FrankenPHP workers, Symfony, Doctrine and the database connection.

- **Locally:** open in the `local` and `dev` environments.
- **In production:** answers 404 unless `INFO_PASSWORD` is set. When it is, the page sits behind HTTP Basic Auth (`INFO_USER` / `INFO_PASSWORD`).

### Xdebug

Xdebug is installed in the dev image in **trigger mode**, so normal requests run at full speed. To debug a single request:

- **Browser:** enable the Xdebug helper extension.
- **curl:** add `?XDEBUG_TRIGGER=1`, or the cookie `XDEBUG_TRIGGER=1`.
- **CLI:** `XDEBUG_TRIGGER=1 bin/console ...`

Your IDE listens on port 9003, with the server name `ticket-api` (`PHP_IDE_CONFIG`). Coverage mode is enabled only by `make test-coverage`.

---

## Deployment (Dokploy)

The base `docker-compose.yml` is the production definition: it runs the image from GHCR, it does not build anything. `docker-compose.override.yml` adds the dev setup on top of it (it builds the `dev` target), and is never used in production.

### The image

A single `Dockerfile` builds every environment from the same base (FrankenPHP, PHP, extensions, Caddyfile, healthcheck), so dev and prod cannot drift apart:

| Target | Used by | Adds |
|--------|---------|------|
| `base` | every stage | FrankenPHP with `intl`, `pdo_pgsql`, `opcache` |
| `dev` | `make build` | Composer, Xdebug, shell tools, your UID |
| `build` | `prod` only | vendors, autoloader, compiled `.env`, warm cache |
| `prod` | CI → GHCR | the app from `build`, nothing else |

On every push to `main` that passes all checks, CI builds `prod` and pushes it as `ghcr.io/<owner>/tickets-api:<git sha>` and `:latest`. The image that passed the tests is the one that runs; rolling back means deploying an older tag. `make build-prod` builds the same image locally.

**What the image ships:**
- **The final image:** FrankenPHP and the PHP extensions only. No Composer, git or build tools.
- **The application:** `prod` dependencies only, an authoritative classmap, a compiled `.env.local.php` and a pre-warmed cache.
- **The FrankenPHP setup:** it runs as `www-data` on port 80, with 4 workers, each recycled every 500 requests (`FRANKENPHP_LOOP_MAX`).
- **Caddy:** security headers (`nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: no-referrer`, no `Server`), bodies over 2 MB cut before PHP, `/health` left out of the access log, and the client IP taken from Traefik's `X-Forwarded-For`.

**How it behaves at runtime:**
- **Health:** the healthcheck calls `/health` through Caddy, the worker and Symfony.
- **Migrations:** with `RUN_MIGRATIONS=true`, the container waits for the database on start. It then creates the `tickets` schema if needed and runs the pending migrations (`--all-or-nothing`).

### Steps

1. **Create a Compose service** in Dokploy, pointing to this repository. The compose path is `docker-compose.yml`. If the GHCR package is private, add `ghcr.io` under Dokploy's Registry with a token that has `read:packages`.
2. **Add a PostgreSQL database and Redis** in the same Dokploy project (or start them from the infrastructure repo on `dokploy-network`).
3. **Fill the Environment tab** using [`.env.prod.example`](.env.prod.example) as a template:

   | Variable | Value |
   |----------|-------|
   | `EXTERNAL_NET` | `dokploy-network`: the network shared with Traefik and the database |
   | `APP_SECRET` | A random value: `php -r 'echo bin2hex(random_bytes(16)), PHP_EOL;'` |
   | `DEFAULT_URI` | The public URL, e.g. `https://api.example.com` |
   | `TRUSTED_HOSTS` | The hosts the app answers to, as regexes: `^api\.example\.com$,^localhost$` (`localhost` is for the healthcheck) |
   | `IMAGE_TAG` | The git SHA to deploy (`latest` if unset) |
   | `DATABASE_URL` | The database's **internal** host from Dokploy's "Internal Credentials" (URL-encode special characters in the password) |
   | `REDIS_URL` | `redis://PASSWORD@INTERNAL_HOST:6379`: the app cache and Doctrine's result cache |
   | `RUN_MIGRATIONS` | `true` |
   | `INFO_USER` / `INFO_PASSWORD` | Optional: enables `/info` behind Basic Auth |

   The image already sets `APP_ENV=prod`, `APP_DEBUG=0`, `SERVER_NAME=:80` and `TRUSTED_PROXIES=private_ranges`.

4. **Domains tab:** add your host for the service `app-tickets` with **container port 80**, and enable HTTPS with Let's Encrypt. Traefik terminates TLS and forwards plain HTTP to the container, which trusts its `X-Forwarded-*` headers.
5. **Deploy.** Then check that `https://<your-host>/health` returns 200 and that `/api/docs` loads.

> `container_name: app-tickets` is fixed in the base compose file, so only one instance of the stack can run per Docker host.

---

## Make commands

Run `make` (or `make help`) for the full list. PHP commands run inside the app container automatically, whether you call them from the host or from `make shell`.

| Command | Description |
|---------|-------------|
| `make build` / `make up` / `make down` | Build the dev image / start the stack (and wait until healthy) / stop it |
| `make build-fresh` / `make build-prod` | Rebuild the dev image without cache / build the production image locally |
| `make restart` | Restart the app container |
| `make logs` / `make shell` | Follow the app logs / open a shell in the container |
| `make install` / `make update` | `composer install` / `composer update` |
| `make db-migrate` / `make db-diff` | Run the migrations / generate a migration from the mapping |
| `make cache-clear` / `make purge` | Clear the Symfony cache / delete cache and logs |
| `make fix-perms` | Give your user and group write access to `var/` |
| `make test-db` | Migrate the test database by hand (test runs already do it) |
| `make test` / `make test-unit` / `make test-integration` / `make test-functional` | Run the test suites |
| `make test-coverage` | Coverage report |
| `make cs` / `make cs-fix` | Check / fix code style |
| `make phpstan` / `make deptrac` | Static analysis / architecture rules |
| `make code-check` / `make ci` | Fast checks without a database / everything CI runs |

---

## Roadmap

These improvements are still open; `REVIEW.md` has the full checklist.

- **Persistence:**
  - a `doctrine_transaction` middleware on the command bus, with no `flush()` in repositories;
  - a single `save()` per repository.
- **Domain events:** move the handlers to an async transport (with an outbox, so an event is never lost nor sent for a rolled-back change) once they do more than log.
- **DBAL types:** base classes for the near-identical ones.
- **API hardening:**
  - CORS;
  - rate limiting;
  - a stateless firewall;
  - protected docs in production;
  - a `/health` check that also checks the database.
- **Authentication:** JWT, for example.
