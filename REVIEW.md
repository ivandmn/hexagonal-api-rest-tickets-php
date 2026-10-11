# Revisión del repositorio

Revisión exhaustiva del proyecto (Domain, Application, Infrastructure, UI, tests, tooling, Docker y docs).
Cada punto lleva casilla para ir marcando lo que se resuelve.

---

## 🔴 1. Bugs reales — ✅ arreglados

Verificado: 191 tests unitarios y 58 funcionales en verde contra un Postgres limpio (6 tests funcionales y 2 unitarios nuevos cubren estos bugs).

- [x] **1.1 `POST /api/tasks` sin `dueDate` devuelve 500.** La propiedad no admite `null` y un test da el `TypeError` por bueno.
  `src/Application/Command/CreateTask/CreateTaskCommand.php:13`, `tests/Unit/Application/Command/CreateTask/CreateTaskCommandTest.php:44`
  ✅ `dueDate` nullable en el command; el test que daba el `TypeError` por bueno ahora comprueba `null`. Test funcional de crear sin `dueDate`.
- [x] **1.2 `UserNotFoundException` extiende `\DomainException` de PHP** en vez de la base del proyecto, y vive en `Exception\Task`.
  `src/Domain/Exception/Task/UserNotFoundException.php:7`
  ✅ Movida a `App\Domain\Exception\User\UserNotFoundException`, que extiende `App\Domain\Exception\DomainException`.
- [x] **1.3 Email duplicado en carrera → 500.** El `UniqueConstraintViolationException` no se traduce a `UserAlreadyExistsException`.
  `src/Infrastructure/Persistence/Doctrine/Repository/DoctrineUserRepository.php` (`save`)
  ✅ `DoctrineUserRepository::save()` traduce `UniqueConstraintViolationException` a `UserAlreadyExistsException` (documentado en la interfaz). Test con dos `save()` del mismo email.
- [x] **1.4 JSON válido que no es objeto (`null`, `[1]`) → 500** en `ApiRequestValidator`, y el `ObjectNormalizer` de `services.yaml` cambia la serialización de toda la app sin que se note.
  `src/Infrastructure/Service/ApiRequestValidator.php:25-54`, `config/services.yaml:102`
  ✅ Corrección: el 500 **no se reproduce** (todas las variantes probadas devuelven 400). Lo real era el efecto global: el `ObjectNormalizer` de `services.yaml` se ha quitado y `allow_extra_attributes: false` se pasa solo en el validador. Mismo comportamiento comprobado.
- [x] **1.5 En el entorno `local` no se escribe ningún log** (`monolog.yaml` no tiene `when@local`). En prod, los `info` se descartan si no hay un error en la misma petición (`fingers_crossed`).
  `config/packages/monolog.yaml`
  ✅ `when@local` reutiliza `when@dev` (ancla YAML) y los ficheros usan `%kernel.environment%`. En prod, el canal `app` va a stderr desde `info`, fuera del `fingers_crossed`.
- [x] **1.6 Paginación rota:** sin filtros devuelve todas las tareas, `meta.total` es el tamaño de la página, `limit` no tiene máximo y `page=0` daría un offset negativo.
  `src/Application/Query/ListTasks/ListTasksQueryHandler.php:31`, `DoctrineTaskRepository`
  ✅ Siempre paginado (`findByFilters` también sin filtros), `meta.total` real con `countByFilters()`, `limit` entre 1 y 100 validado en el DTO y la query normaliza `page`/`limit`.
- [x] **1.7 El update no puede borrar `dueDate`**, y reenviar la misma fecha cuando ya ha vencido lanza "fecha en el pasado".
  `src/Application/Command/UpdateTask/UpdateTaskCommandHandler.php:35-53`
  ✅ `"dueDate": null` la borra (flag `clearDueDate` en el command); sin la clave se mantiene. Reenviar la fecha actual es un no-op aunque ya haya vencido.
- [x] **1.8 `TaskDescription` acepta `?string`** pero `value()` devuelve `string` (con `null` da `TypeError`).
  `src/Domain/ValueObject/Task/TaskDescription.php:13`
  ✅ `private string $value`: `null` lo rechaza la firma del constructor.
- [x] **1.9 `EventDispatcher` usa `App\Domain\Model\DomainEntity`, que no existe.** Código muerto registrado como servicio.
  `src/Application/Event/EventDispatcher.php:8`
  ✅ Borrado (nada lo usaba). El resto del event bus queda para el punto 2.7.
- [x] **1.10 Unos 15 tests usan fechas fijas de 2030** y empezarán a fallar ese año (`TaskDueDate` rechaza fechas pasadas).
  Tests de responses, handlers, `TaskDueDateTest`, `TaskFactoryTest`.
  ✅ Helper `tests/Support/FutureDate` (siempre el año siguiente, determinista) en los tests que pasan por `TaskDueDate`. Los `createdAt`/`updatedAt` de 2030 no caducan y se quedan.

---

## 🟠 2. Arquitectura

- [ ] **2.1 Quitar las 79 violaciones UI → Infrastructure.**
  - Mover a `src/UI/Http/` lo que pertenece al adaptador HTTP: `ApiResponse`, los DTOs de petición, `ApiRequestValidator` y las excepciones `InvalidRequest*`.
  - Borrar `BaseController` (obliga a inyectar los dos buses en cada controller).
  - Inyectar `LoggerInterface` en vez de `MonologLogger`.
  🟡 Parcial: `ApiResponse`, DTOs, validación y excepciones de request ya viven en UI (79 → 8 violaciones). Queda `BaseController` (Infrastructure) y el logger concreto ya no se inyecta en controllers.
- [x] **2.2 Un único listener de excepciones para `/api`.** Sustituye los 8 try/catch copiados con su mapeo excepción → código HTTP (que ya no coinciden entre sí) y devuelve JSON también en 404/405/422, que hoy salen como HTML.
  ✅ `ApiExceptionListener` (Problem Details, RFC 9457) es el único sitio que construye errores; mapeo en `config/packages/error_mapping.yaml`. Ver README › Error Handling.
- [x] **2.3 DTOs tipados con `#[MapRequestPayload]` / `#[MapQueryString]`** en lugar del validador hecho a mano, devolviendo todas las violaciones y no solo la primera.
  ✅ DTOs tipados en `src/UI/Http/Request` con `#[MapRequestPayload]` / `#[MapQueryString]`; reglas de valor delegadas al VO con `#[ValidVO]`; se devuelven todas las violaciones.
- [ ] **2.4 CQRS de verdad:**
  - Los commands devuelven `void`.
  - Una sola `TaskView` en lugar de 4 copias de la misma respuesta.
  - El formato JSON pasa a la capa UI (hoy las responses de Application hacen `jsonSerialize`).
- [ ] **2.5 `TaskDueDate`:**
  - Parsear la fecha en el value object (`fromString`, detectando fechas desbordadas como el 30 de febrero) en lugar de en los commands.
  - Inyectar un `Clock` (PSR-20) para la regla "no en el pasado".
  - Un `reconstitute()` en el dominio en lugar de la reflexión de `TaskDueDateType`.
- [ ] **2.6 Persistencia:**
  - Middleware `doctrine_transaction` en el bus de commands y quitar el `flush()` de los repositorios.
  - `save()` único en lugar de `create`/`update`.
  - Un `TaskCriteria` tipado en lugar de `array $filters`.
  - Borrar los métodos que no se usan (`findByUserId`, `UserRepositoryInterface::delete`).
- [ ] **2.7 Event bus montado pero sin usar:** o se emiten eventos de dominio, o se quita todo.
- [ ] **2.8 Tipos DBAL:** clases base para los 9 casi idénticos, y quitar `getName()` (DBAL 4 ya no lo usa).
- [ ] **2.9 Detalles de dominio:**
  - `Task::getId()` debería devolver `TaskId`, no `Uuid`.
  - `Uuid` abstracto.
  - Constructor privado más un constructor con nombre en `Task`.
  - `User` es anémico y su `updatedAt` nunca cambia.
  - Medir longitudes con `mb_strlen(trim())`, no con `strlen`.

---

## 🟡 3. Semántica de la API

- [ ] **3.1 201 + `Location`** al crear (hoy 200).
- [ ] **3.2 PATCH** para el update parcial (hoy PUT, pero solo cambia lo que envías).
- [ ] **3.3 200 con `[]`** para listas vacías (hoy 204, que pierde el `meta`).
- [ ] **3.4 Quitar el anidado `data.data`** de los listados.
- [x] **3.5 422** para fecha en el pasado (hoy 409; dejar el 409 para conflictos de estado).
  ✅ `TASK_DUE_DATE_IN_PAST` → 422 (409 queda para conflictos de estado).
- [x] **3.6 `requirements: ['id' => Requirement::UUID]`** en las rutas (hoy `{id}` acepta cualquier texto).
  ✅ `requirements: ['id' => Requirement::UUID]` en todas las rutas con `{id}`: un id que no es UUID da 404.
- [ ] **3.7 camelCase en todas las respuestas** (hoy `task_id`/`user_id` conviven con `assignedTo`/`dueDate`).
- [ ] **3.8 Docs solo en dev**, o protegidas (`/api/docs` es público en producción).
- [ ] **3.9 CORS, rate limiting y API stateless** (`session: false`).
- [ ] **3.10 `StatusController`:** sin `methods:`, `/` devuelve 200 vacío y `/health` no comprueba la BD.

---

## 🧪 4. Tests

- [ ] **4.1 BD de test propia.** Hoy los funcionales usan la BD externa (`.env.test` apunta a un host `db` que no existe y `dbname_suffix` está comentado).
  - Servicio Postgres en el compose de desarrollo.
  - Forzar `DATABASE_URL` en `phpunit.dist.xml`.
  - Target `make test-db` que cree el schema y migre.
- [ ] **4.2 27 tests con el namespace mal** (`App\Tests\Application\…` sin `Unit`); PSR-4 no los resuelve.
- [ ] **4.3 Suite de integración:** repositorios, los 9 tipos DBAL, leer una fecha pasada guardada, migraciones.
- [ ] **4.4 Tests de los buses** (que desenvuelven `HandlerFailedException`) y del validador (JSON inválido).
- [ ] **4.5 Reloj falso (`MockClock`)** para quitar las dependencias de `now`.
- [ ] **4.6 Object mothers y repositorios en memoria** en lugar de 43 `new Task(...)` y mocks que comprueban textos de log.
- [ ] **4.7 Asserts más estrictos:** quitar los que comprueban `instanceof` de lo recién construido, los que aceptan `[404, 400]` y los tests `*_currently` que fijan bugs como si fueran comportamiento correcto.
- [ ] **4.8 Smoke tests** de `/health` y `/api/docs.json`.

---

## 🛠️ 5. Tooling, Docker y config

- [ ] **5.1 PHPStan.**
  - `tests/Support/PhpStan/Kernel.php:8` hace `dirname(__DIR__ . '/../../../vendor/autoload.php')`, que apunta al directorio y no al fichero.
  - `phpstan.dist.neon` lee `…KernelDevDebugContainer.xml`, mientras que `make phpstan` genera `…KernelLocalDebugContainer.xml`.
  - Solución: fijar el entorno `test` en los tres sitios.
- [ ] **5.2 deptrac:** quitar restos de otro proyecto (`Farmapremium`, `Google|Firebase`, el `skip_violations` de `EmitTestApplePassCli`).
- [ ] **5.3 CI con GitHub Actions:** cs-fixer, deptrac, PHPStan y tests unitarios y funcionales con un servicio Postgres (hoy no hay pipeline).
- [ ] **5.4 Composer:**
  - Quitar `guzzlehttp/guzzle`, `predis/predis` y `twig/extra-bundle` (no se usan).
  - Añadir `ext-pdo_pgsql`.
  - `"php": ">=8.5"` admite PHP 9: usar `~8.5.0` y fijar `config.platform`.
- [ ] **5.5 Makefile:**
  - Añadir `.PHONY`.
  - Prefijo `docker compose exec` para los comandos PHP cuando se lanzan desde el host.
  - Quitar el `chmod 777` de `fix-perms`.
  - `restart` usa un profile `supervisor` que no existe.
  - Faltan `test`, `test-functional`, `test-db` y `ci`.
- [ ] **5.6 `.env`:** la convención de Symfony es commitear un `.env` con valores por defecto seguros y dejar los secretos en `.env.local`. Hoy está en `.gitignore`, así que un clon nuevo ni arranca los tests. Sobra uno de `.env.example` / `.env.local.example`.
- [ ] **5.7 Dockerfile de producción (~860 MB):**
  - Multi-stage.
  - Quitar extensiones que no se usan (amqp, apcu, exif, gd, pcntl, redis, zip).
  - Quitar `git`, `acl`, `file` y Composer de la imagen final.
  - Una base común para dev y prod.
- [ ] **5.8 `.dockerignore`:** excluir `bin/*` salvo `bin/console` (los proxies de las herramientas de desarrollo acaban rotos en la imagen de prod), cachés y configs de tooling.
- [ ] **5.9 Build de dev:** `USER_ID`/`GROUP_ID` sin valor por defecto rompen el build si falta `.env.local` (usar `${USER_ID:-1000}`).

---

## 📄 6. Docs y limpieza

- [ ] **6.1 README:**
  - Título y autor dicen "Farmapremium API" y la URL de clonado apunta a ese repo.
  - Cita comandos que no existen (`make test`, `composer-install`, `bash`, `prune`).
  - Dice que la documentación está en `/` (está en `/api/docs`) y cita `phpunit.xml.dist` (es `phpunit.dist.xml`).
  - Falta la capa `UI/` en el árbol.
  - No hay sección de despliegue en Dokploy.
- [ ] **6.2 Ficheros que sobran:**
  - `public/docs/openapi.yml` (spec escrito a mano y desfasado; ya lo genera Nelmio).
  - `TEST.md` es el enunciado original: moverlo a `docs/`.
  - El `Caddyfile` de la raíz (idéntico a `etc/caddy/Caddyfile` y sin usar).
  - `AppFixtures` vacío y bloques de config que solo tienen comentarios.
- [ ] **6.3 Comentarios en español** en un proyecto escrito en inglés (`framework.yaml`, `nelmio_api_doc.yaml`).
- [ ] **6.4 `etc/bash/.gitconfig`:** `credential.helper = store` guarda las credenciales en claro dentro del contenedor.

---

## Descartado

- **Quitar `container_name`:** decisión tomada, se mantiene.
- **Mover `CREATE SCHEMA` solo a la migración:** no funciona; Doctrine crea antes su tabla de control en `tickets`.
- **"Sin `VAR_DUMPER_SERVER` no compila":** compila; solo afecta a `dump()`.
- **Credenciales en el historial de git:** revisado, no aparecen.

---

## Orden propuesto

1. Bugs (§1) y PHPStan + deptrac + CI (§5.1–5.3).
2. BD de test, namespaces y suite de integración (§4.1–4.3).
3. Capas, listener de excepciones y DTOs tipados (§2.1–2.3), junto con la semántica HTTP (§3).
4. CQRS, `TaskDueDate`/`Clock` y persistencia (§2.4–2.6).
5. Docker, `.env`, Composer y docs (§5.4–5.9, §6).
