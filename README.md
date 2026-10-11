# 🧩 Farmapremium API

A PHP backend project implementing a **task management system** with a **hexagonal architecture**, built using **Symfony**, **Doctrine ORM**, and fully **Dockerized**.

---

## 🚀 Installation & Setup

### 1. Clone the repository
```bash
git clone https://github.com/ivandmn/farmapremium-php-api-rest.git
cd farmapremium-php-api-rest
```

### 2. Copy environment file
```bash
cp .env.example .env
```

You can override environment variables using:
- `.env.dev.local` (for local development)
- `.env.test.local` (for test configuration)
- `.env.prod.local` (for production)

Also copy phpunit configuration for tests
```bash
cp phpunit.xml.dist phpunit.xml
```

### 3. Start the Docker containers
First build containers
```bash
make build
```
And then start them
```bash
make up
```

> 🐳 This command automatically loads all existing `.env.*` files depending on your environment.

### 4. Install dependencies
```bash
make composer-install
```

### 5. Run database migrations
```bash
docker compose exec app php bin/console doctrine:migrations:migrate
```

### 6. Access the application

| Environment  | URL |
|--------------|-----|
| API Base     | [http://localhost:8080/api](http://localhost:8080/api) |
| API Docs     | [http://localhost:8080/](http://localhost:8080/) |
| App info (local/dev only) | [http://localhost:8080/info](http://localhost:8080/info) — PHP, extensions, OPcache, Xdebug, FrankenPHP, Symfony, Doctrine… (`?format=json`, `?sections=php,doctrine`) |

---

## 🧪 Running Tests

### 1. Unit & Functional Tests
```bash
make test
```

This will:
- Create the test database (`APP_ENV=test`)
- Run migrations in test mode
- Execute all PHPUnit test suites

> ✅ Expected coverage: at least **70%** on Domain and Application layers.

---

## 📘 API Documentation

The full API specification is available in OpenAPI 3 format:

- **YAML spec**: [`/public/docs/openapi.yaml`](public/docs/openapi.yaml)
- **Swagger UI**: [http://localhost:8080/](http://localhost:8080)

If you prefer a lightweight version using ReDoc:
```twig
<redoc spec-url="{{ asset('docs/openapi.yaml') }}"></redoc>
```

---

## 🚨 Error Handling

Every error under `/api` is a [Problem Details](https://www.rfc-editor.org/rfc/rfc9457) response (`application/problem+json`), built in a single place: `App\UI\Http\Exception\ApiExceptionListener`. Controllers never catch exceptions.

```json
{
  "type": "VALIDATION_ERROR",
  "title": "The submitted data is not valid.",
  "status": 422,
  "errors": [
    { "field": "title", "code": "VALUE_TOO_SHORT", "message": "This value must be at least 5 characters long.", "params": { "min": 5 } }
  ],
  "traceId": "a3f9c2e17b4d8e05"
}
```

Clients must branch on `type` / `errors[].code`, never on the text.

| Status | When | Examples |
|--------|------|----------|
| 400 | Malformed request | `REQUEST_MALFORMED_JSON`, `REQUEST_EMPTY_BODY`, `REQUEST_INVALID_BODY` |
| 404 | Missing resource or unknown route | `TASK_NOT_FOUND`, `USER_NOT_FOUND`, `NOT_FOUND` |
| 409 | State incompatible with the operation | `TASK_STATUS_TRANSITION_NOT_ALLOWED`, `TASK_DELETION_NOT_ALLOWED`, `USER_ALREADY_EXISTS`, `USER_DELETION_NOT_ALLOWED` |
| 415 | Body is not JSON | `UNSUPPORTED_MEDIA_TYPE` |
| 422 | Invalid values (every invalid field listed) or broken business rule | `VALIDATION_ERROR`, `TASK_DUE_DATE_IN_PAST` |
| 500 | Unexpected error (details only with `APP_DEBUG=1`) | `INTERNAL_ERROR` |

**How it fits together**

- **Domain** exceptions extend `App\Domain\Exception\DomainException` and carry a stable `errorCode` plus `params`, with no HTTP knowledge. Value Objects throw the generic `InvalidValueException` (`VALUE_*` codes).
- **Request DTOs** (`src/UI/Http/Request`) only check structure (`NotBlank`, `Range`); value rules are delegated to the Value Object with `#[ValidVO(...)]`, so each rule lives in one place.
- **`config/packages/error_mapping.yaml`** maps every code to its HTTP status, public message (with `{placeholders}`) and optional field.

**Adding an error**

1. Create the exception in `src/Domain/Exception/<Context>/` with a `public const ERROR_CODE = 'CONTEXT_SOMETHING'` (UPPER_SNAKE_CASE, never reused).
2. Map it in `config/packages/error_mapping.yaml`.
3. `ErrorCodeCoverageTest` fails until every declared code is mapped (and nothing else is).

---

## 🧠 Technical Decisions

1. **Hexagonal Architecture**  
   - Clear separation between Domain, Application, and Infrastructure layers.  
   - Domain models are pure and framework-independent.  

2. **SOLID Principles**  
   - Each class has a single responsibility.  
   - Dependency inversion applied via interfaces and value objects.  

3. **Doctrine ORM**  
   - Used for persistence in PostgreSQL.  
   - Entities mapped via PHP attributes.  

4. **DTOs & Value Objects**  
   - All inputs are validated through DTOs.  
   - Value Objects encapsulate domain constraints (e.g., `TaskId`, `TaskPriority`).  

5. **Testing Strategy**  
   - Unit tests for core logic.  
   - Functional tests for controllers and endpoints.  
   - `dama/doctrine-test-bundle` used to isolate test database transactions.  

6. **Makefile Automation**  
   - Simplified environment management:  
     - `make up` → start  
     - `make down` → stop  
     - `make test` → run test suite  
     - `make composer-install` → install dependencies  

---

## 🚀 Possible Future Improvements

1. **Implement JWT-based authentication** for secure user login and API access control.
2. **Enhance task filtering** in the `/api/tasks` endpoint
3. **Set up CI/CD pipelines with GitHub Actions** for automated testing, building, and deployment.
4. **Add a validation error formatting middleware** to standardize API error responses.
5. **Integrate static analysis tools (PHPStan or Psalm)** and enforce strict typing across the codebase.
6. **Expand automated test coverage**, including unit, integration, and functional tests.
7. **Refactor and optimize existing code** to improve readability, maintainability, and performance.
8. **Define additional domain rules and invariants** to strengthen business logic consistency.
9. **Add user update (`PUT /api/users/{id}`)** and **delete (`DELETE /api/users/{id}`)** endpoints.
10. **Introduce domain events and event handlers** to decouple components and improve extensibility.


---

## 🧱 Project Structure

```
/src
├── Application/
│   ├── Command/   # Commands + CommandHandlers (write side)
│   ├── Query/     # Queries + QueryHandlers (read side)
│   └── Service/
├── Domain/
│   ├── Model/
│   ├── Repository/
│   ├── ValueObject/
│   └── Exception/
└── Infrastructure/
    ├── Persistence/Doctrine/
    │   ├── Mapping/     # XML mapping of the domain models (*.orm.xml)
    │   ├── Type/        # DBAL types for the value objects
    │   ├── Repository/
    │   └── Migration/
    └── Http/
```

---

## 🧰 Useful Commands

| Command | Description |
|----------|-------------|
| `make up` | Start containers |
| `make down` | Stop containers |
| `make restart` | Restart the app |
| `make composer-install` | Install dependencies |
| `make test` | Run all tests |
| `make logs` | Follow container logs |
| `make bash` | Enter app container |
| `make prune` | Clean up Docker resources |

---

## 🧑‍💻 Author

**Farmapremium Backend API**  
Developed as part of a PHP backend technical test.
