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

- [x] **2.1 Quitar las 79 violaciones UI → Infrastructure.**
  - Mover a `src/UI/Http/` lo que pertenece al adaptador HTTP: `ApiResponse`, los DTOs de petición, `ApiRequestValidator` y las excepciones `InvalidRequest*`.
  - Borrar `BaseController` (obliga a inyectar los dos buses en cada controller).
  - Inyectar `LoggerInterface` en vez de `MonologLogger`.
  ✅ `ApiResponse`, DTOs, validación y excepciones de request viven en UI. `BaseController` no se borra sino que pasa a UI como `App\UI\Http\Controller\Api\ApiController` (abstracto, `ask()`/`dispatch()`): es un adaptador de entrada, y quitarlo obligaría a repetir los dos buses en 10 controllers. El logger concreto ya no se inyecta en controllers. **deptrac: 0 violaciones, 0 sin cubrir.**
- [x] **2.2 Un único listener de excepciones para `/api`.** Sustituye los 8 try/catch copiados con su mapeo excepción → código HTTP (que ya no coinciden entre sí) y devuelve JSON también en 404/405/422, que hoy salen como HTML.
  ✅ `ApiExceptionListener` (Problem Details, RFC 9457) es el único sitio que construye errores; mapeo en `config/packages/error_mapping.yaml`. Ver README › Error Handling.
- [x] **2.3 DTOs tipados con `#[MapRequestPayload]` / `#[MapQueryString]`** en lugar del validador hecho a mano, devolviendo todas las violaciones y no solo la primera.
  ✅ DTOs tipados en `src/UI/Http/Request` con `#[MapRequestPayload]` / `#[MapQueryString]`; reglas de valor delegadas al VO con `#[ValidVO]`; se devuelven todas las violaciones.
- [x] **2.4 CQRS de verdad:**
  - Los commands devuelven `void`.
  - Una sola `TaskView` en lugar de 4 copias de la misma respuesta.
  - El formato JSON pasa a la capa UI (hoy las responses de Application hacen `jsonSerialize`).
  ✅ `CommandBusInterface::dispatch(): void` y todos los handlers de command devuelven `void`. Los 8 `*Response` se borran: las queries devuelven read models en `Application/DTO/Views` (`TaskView`, `AssignedUserView`, `TaskListView`, `UserView`, `UserListView`). El JSON lo construye `UI/Http/Response/ApiResponder` con el Serializer (fechas RFC 3339, snake_case). Para crear, el controller genera el id, despacha el command y lee la vista con la query (nueva `GetUserDetailsQuery` para el usuario).
- [x] **2.5 `TaskDueDate`:**
  - Parsear la fecha en el value object (`fromString`, detectando fechas desbordadas como el 30 de febrero) en lugar de en los commands.
  - Inyectar un `Clock` (PSR-20) para la regla "no en el pasado".
  - Un `reconstitute()` en el dominio en lugar de la reflexión de `TaskDueDateType`.
  ✅ `TaskDueDate` extiende el nuevo VO `DateTime` (`fromString` RFC 3339 que rechaza desbordes, normaliza a UTC). El VO es puro: la regla "no en el pasado" vive en `Task::create(..., $now)` y `Task::changeDueDate($date, $now)`, y los handlers pasan `$clock->now()` (`symfony/clock`, PSR-20; `MockClock` en tests). **`reconstitute()` no hace falta:** al no validar contra el reloj en el constructor, `TaskDueDateType` hidrata con el constructor normal y la reflexión desaparece.
- [ ] **2.6 Persistencia:**
  - Middleware `doctrine_transaction` en el bus de commands y quitar el `flush()` de los repositorios.
  - `save()` único en lugar de `create`/`update`.
  - Un `TaskCriteria` tipado en lugar de `array $filters`.
  - Borrar los métodos que no se usan (`findByUserId`, `UserRepositoryInterface::delete`).
  🟡 Parcial: `TaskCriteria` (enums tipados) sustituye a `array $filters` con `findByCriteria()`/`countByCriteria()`, con orden estable (`created_at DESC, id`). Fuera `findAll`, `findByUserId`, `findByFilters` y `countByFilters`. `UserRepositoryInterface::delete` **se queda**: ahora lo usa `DELETE /api/users/{id}`. Bug encontrado al testear: `findBy()` convertía los enums a string antes del tipo DBAL (500 al filtrar); ahora se usa QueryBuilder con parámetros tipados. Quedan la transacción en el bus y el `save()` único.
- [ ] **2.7 Event bus montado pero sin usar:** o se emiten eventos de dominio, o se quita todo.
- [ ] **2.8 Tipos DBAL:** clases base para los 9 casi idénticos, y quitar `getName()` (DBAL 4 ya no lo usa).
- [ ] **2.9 Detalles de dominio:**
  - `Task::getId()` debería devolver `TaskId`, no `Uuid`.
  - `Uuid` abstracto.
  - Constructor privado más un constructor con nombre en `Task`.
  - `User` es anémico y su `updatedAt` nunca cambia.
  - Medir longitudes con `mb_strlen(trim())`, no con `strlen`.
  🟡 Parcial: `TaskTitle`, `TaskDescription`, `UserName` y `Email` recortan con `trim()` y miden con `mb_strlen()` (el valor guardado es el recortado; `Email` comprueba la longitud antes del formato). El resto sigue pendiente.

---

## 🟡 3. Semántica de la API

- [x] **3.1 201 + `Location`** al crear (hoy 200).
  ✅ `POST /api/tasks` y `POST /api/users` → 201 con `Location` absoluto, que apunta a un recurso real (nuevo `GET /api/users/{id}`).
- [x] **3.2 PATCH** para el update parcial (hoy PUT, pero solo cambia lo que envías).
  ✅ `PATCH /api/tasks/{id}`; `PUT` da 405. `"due_date": null` borra la fecha y omitir la clave la mantiene.
- [x] **3.3 200 con `[]`** para listas vacías (hoy 204, que pierde el `meta`).
  ✅ Listas vacías: 200 con `"data": []` y su `meta`. `DELETE` de tareas y usuarios → 204 sin cuerpo.
- [x] **3.4 Quitar el anidado `data.data`** de los listados.
  ✅ Colecciones: `{"status": "success", "data": [...], "meta": {...}}`.
- [x] **3.5 422** para fecha en el pasado (hoy 409; dejar el 409 para conflictos de estado).
  ✅ `TASK_DUE_DATE_IN_PAST` → 422 (409 queda para conflictos de estado).
- [x] **3.6 `requirements: ['id' => Requirement::UUID]`** en las rutas (hoy `{id}` acepta cualquier texto).
  ✅ `requirements: ['id' => Requirement::UUID]` en todas las rutas con `{id}`: un id que no es UUID da 404.
- [x] **3.7 camelCase en todas las respuestas** (hoy `task_id`/`user_id` conviven con `assignedTo`/`dueDate`).
  ✅ Decisión: **snake_case** en todo (peticiones, respuestas, `errors[].field`, `params` y `trace_id`). Name converter `camel_case_to_snake_case` global en el Serializer; el listener de errores usa el mismo para los paths de validación. Un `dueDate` en el payload es ahora campo desconocido (422).
- [ ] **3.8 Docs solo en dev**, o protegidas (`/api/docs` es público en producción).
- [ ] **3.9 CORS, rate limiting y API stateless** (`session: false`).
- [ ] **3.10 `StatusController`:** sin `methods:`, `/` devuelve 200 vacío y `/health` no comprueba la BD.

---

## 🧪 4. Tests

- [x] **4.1 BD de test propia.** Hoy los funcionales usan la BD externa (`.env.test` apunta a un host `db` que no existe y `dbname_suffix` está comentado).
  - Servicio Postgres en el compose de desarrollo.
  - Forzar `DATABASE_URL` en `phpunit.dist.xml`.
  - Target `make test-db` que cree el schema y migre.
  ✅ Con otra solución, porque forzar `DATABASE_URL` en phpunit no cubre `bin/console --env=test`. `when@test` lee `TEST_DATABASE_URL`, que solo define `.env.test`, así que el `DATABASE_URL` real que inyecta el `env_file` del contenedor ya no puede ganar. Apunta al servicio `tickets-db-test` del compose de dev (Postgres 18 en tmpfs). `make test-db` crea el schema y migra; CI usa su propio servicio.
- [x] **4.2 27 tests con el namespace mal** (`App\Tests\Application\…` sin `Unit`); PSR-4 no los resuelve.
  ✅ Todos los namespaces de `tests/` coinciden con su ruta.
- [x] **4.3 Suite de integración:** repositorios, los 9 tipos DBAL, leer una fecha pasada guardada, migraciones.
  ✅ Suite `integration` (`tests/Integration`, `make test-integration`, paso propio en CI): ida y vuelta de una tarea con todos los campos contra Postgres (con `clear()` del EntityManager y una fecha límite ya pasada), update y delete, `TaskCriteria` y paginación, email duplicado, y `MigrationsTest` (`doctrine:migrations:up-to-date` + `doctrine:schema:validate`). Los 9 tipos DBAL: `TaskDueDateTypeTest` y `ValueObjectTypesTest` (escritura, lectura, null, tipos inválidos y registro en `doctrine.yaml`).
- [x] **4.4 Tests de los buses** (que desenvuelven `HandlerFailedException`) y del validador (JSON inválido).
  ✅ `tests/Unit/Infrastructure/Bus/Symfony`: resultado del handler, excepción original (misma instancia), mensaje sin handler y eventos con varios handlers. El validador ya no existe: el JSON inválido lo cubren `PayloadTest` y `ApiErrorHandlingTest`.
- [x] **4.5 Reloj falso (`MockClock`)** para quitar las dependencias de `now`.
  ✅ Los handlers que dependen del tiempo reciben `ClockInterface`; sus tests usan `MockClock` con un instante fijo.
- [x] **4.6 Object mothers y repositorios en memoria** en lugar de 43 `new Task(...)` y mocks que comprueban textos de log.
  ✅ `tests/Support/Mother`, `Double/InMemoryTaskRepository`, `Double/InMemoryUserRepository` y `Double/SpyAppLogger`. Los tests de Application comprueban estado, no llamadas a mocks.
- [x] **4.7 Asserts más estrictos:** quitar los que comprueban `instanceof` de lo recién construido, los que aceptan `[404, 400]` y los tests `*_currently` que fijan bugs como si fueran comportamiento correcto.
  ✅ Fuera los `assertInstanceOf` sobre lo recién construido o con tipo de retorno declarado (`assertNotNull` donde el valor es nullable); solo quedan los que prueban algo (que un factory devuelve la subclase). Ya no quedan `[404, 400]` ni `*_currently`. De paso, `Task::getId()` devolvía `Uuid` en vez de `TaskId`.
- [x] **4.8 Smoke tests** de `/health` y `/api/docs.json`.
  ✅ `tests/Functional/SmokeTest.php`: `/health`, `/status` y `/` responden 200 sin cuerpo; `/api/docs.json` es OpenAPI 3 con exactamente las 10 operaciones y el body documentado de cada una; `/api/docs` renderiza.

---

## 🛠️ 5. Tooling, Docker y config

- [x] **5.1 PHPStan.**
  - `tests/Support/PhpStan/Kernel.php:8` hace `dirname(__DIR__ . '/../../../vendor/autoload.php')`, que apunta al directorio y no al fichero.
  - `phpstan.dist.neon` lee `…KernelDevDebugContainer.xml`, mientras que `make phpstan` genera `…KernelLocalDebugContainer.xml`.
  - Solución: fijar el entorno `test` en los tres sitios.
  ✅ Entorno `test` fijado en `Kernel.php` (con `require_once` corregido), `phpstan.dist.neon` y `make phpstan`. Con el contenedor correcto salieron 222 errores de nivel 10 que nunca se habían visto. Están arreglados con tipos reales (narrowing, shapes `Problem`/`FieldError`, `@phpstan-consistent-constructor`), sin baseline. Única excepción documentada: el JSON decodificado de los tests funcionales (`mixed` por naturaleza). `config/reference.php` (generado) queda excluido. **Nivel 10: 0 errores.**
- [x] **5.2 deptrac:** quitar restos de otro proyecto (`Farmapremium`, `Google|Firebase`, el `skip_violations` de `EmitTestApplePassCli`).
  ✅ Reescrito: capas Domain/Application/Infrastructure/UI más capas de vendor (`CoreLibraries` = ramsey/uuid y PSR-20, `Logging` = PSR-3, `Framework`). Así "nada de framework en el núcleo" se comprueba en vez de solo documentarse. Caché en `var/`. 0 violaciones, 0 sin cubrir.
- [x] **5.3 CI con GitHub Actions:** cs-fixer, deptrac, PHPStan y tests unitarios y funcionales con un servicio Postgres (hoy no hay pipeline).
  ✅ `.github/workflows/ci.yml`: el job `quality` pasa cs-fixer, deptrac y PHPStan; el job `tests` levanta `postgres:18-alpine`, crea el schema, migra, valida el mapping y lanza unit y functional. Simulado en local en un directorio limpio y con entorno vacío: todo en verde.
- [x] **5.4 Composer:**
  - Quitar `guzzlehttp/guzzle`, `predis/predis` y `twig/extra-bundle` (no se usan).
  - Añadir `ext-pdo_pgsql`.
  - `"php": ">=8.5"` admite PHP 9: usar `~8.5.0` y fijar `config.platform`.
  ✅ Quitados (y su `allow-plugins` de `php-http/discovery` y el bundle de `config/bundles.php`); `ext-pdo_pgsql`; `"php": "~8.5.0"` y `config.platform.php = 8.5.0`.
- [x] **5.5 Makefile:**
  - Añadir `.PHONY`.
  - Prefijo `docker compose exec` para los comandos PHP cuando se lanzan desde el host.
  - Quitar el `chmod 777` de `fix-perms`.
  - `restart` usa un profile `supervisor` que no existe.
  - Faltan `test`, `test-functional`, `test-db` y `ci`.
  ✅ `.PHONY` y `.DEFAULT_GOAL := help`; prefijo `EXEC` (`docker compose exec app-tickets`, vacío dentro del contenedor). `fix-perms` usa `chmod -R ug+rwX var` y `restart` reinicia `app-tickets`. Nuevos `test`, `test-functional`, `test-db` y `ci`; `code-check` queda para lo rápido sin BD.
- [ ] **5.6 `.env`:** la convención de Symfony es commitear un `.env` con valores por defecto seguros y dejar los secretos en `.env.local`. Hoy está en `.gitignore`, así que un clon nuevo ni arranca los tests. Sobra uno de `.env.example` / `.env.local.example`.
- [ ] **5.7 Dockerfile de producción (~860 MB):**
  - Multi-stage.
  - Quitar extensiones que no se usan (amqp, apcu, exif, gd, pcntl, redis, zip).
  - Quitar `git`, `acl`, `file` y Composer de la imagen final.
  - Una base común para dev y prod.
  🟡 Parcial: multi-stage (`base` → `build` con Composer → `prod`). La imagen final no lleva Composer, git, acl, file (venía en la imagen base: se purga en la etapa final) ni gettext, y `libcap2-bin` se purga tras el `setcap`. Extensiones: solo `intl`, `pdo_pgsql` y `opcache` (fuera amqp, apcu, exif, gd, pcntl, redis y zip, también en dev y CI). Probada: arranca como `www-data` en :80, `/health` 200, `/api/docs.json` 200, `/info` 404. Queda la base común con dev (la imagen sigue en ~785 MB, casi todo es la base de FrankenPHP).
- [x] **5.8 `.dockerignore`:** excluir `bin/*` salvo `bin/console` (los proxies de las herramientas de desarrollo acaban rotos en la imagen de prod), cachés y configs de tooling.
  ✅ `bin/*` salvo `bin/console`, tests, cachés (`.deptrac.cache`, `.php-cs-fixer.cache`, `.phpunit.cache`), configs de tooling, `.github/`, `*.md`, Makefile, compose y el `Caddyfile` de la raíz.
- [ ] **5.9 Build de dev:** `USER_ID`/`GROUP_ID` sin valor por defecto rompen el build si falta `.env.local` (usar `${USER_ID:-1000}`).

---

## 📄 6. Docs y limpieza

- [x] **6.1 README:**
  - Título y autor dicen "Farmapremium API" y la URL de clonado apunta a ese repo.
  - Cita comandos que no existen (`make test`, `composer-install`, `bash`, `prune`).
  - Dice que la documentación está en `/` (está en `/api/docs`) y cita `phpunit.xml.dist` (es `phpunit.dist.xml`).
  - Falta la capa `UI/` en el árbol.
  - No hay sección de despliegue en Dokploy.
  ✅ Reescrito entero: contrato de la API, endpoints, errores, arquitectura (con `UI/` y `Application/DTO/Views`), tests, calidad, comandos reales y despliegue en Dokploy.
- [ ] **6.2 Ficheros que sobran:**
  - `public/docs/openapi.yml` (spec escrito a mano y desfasado; ya lo genera Nelmio). ✅ Borrado.
  - `TEST.md` es el enunciado original: moverlo a `docs/`.
  - El `Caddyfile` de la raíz (idéntico a `etc/caddy/Caddyfile` y sin usar).
  - `AppFixtures` vacío y bloques de config que solo tienen comentarios.
- [x] **6.3 Comentarios en español** en un proyecto escrito en inglés (`framework.yaml`, `nelmio_api_doc.yaml`).
  ✅ Traducidos. Además, la descripción de la API en Nelmio resume el contrato (snake_case, envoltorio, Problem Details, UTC).
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
