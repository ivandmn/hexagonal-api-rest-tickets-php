# Propuestas de mejora de configuración (dev + prod)

Revisión exhaustiva de **toda la configuración** del repo: Docker/Compose, Dockerfiles, entrypoint, PHP (`php.ini`),
Caddy/FrankenPHP, Composer, `.env*`, `config/packages/*.yaml`, `services.yaml`, rutas, PHPUnit, PHPStan, deptrac,
php-cs-fixer, Makefile y `.dockerignore`.

Cada punto explica **qué hay**, **qué propongo** y **por qué** (qué hacen los proyectos profesionales y qué problema evita).
Cuando un punto ya aparece en `REVIEW.md` se indica (`→ REVIEW §x.y`) y aquí se amplía con el detalle de configuración.

Leyenda: 🔴 crítico (seguridad / riesgo de datos) · 🟠 importante · 🟡 mejora · 🟢 opcional / nice to have

> Verificado sobre el contenedor en marcha: FrankenPHP 1.13.0, Caddy 2.11.7, PHP 8.5.11 (ZTS), Symfony 7.4.20.
> `composer validate --strict` OK, `composer audit` sin vulnerabilidades, `check-platform-reqs` OK.

---

## Índice

0. [Resumen y prioridades](#0-resumen-y-prioridades)
1. [🔴 Críticos: base de datos y entornos](#1--críticos-base-de-datos-y-entornos)
2. [Docker y Compose](#2-docker-y-compose)
3. [FrankenPHP y Caddy](#3-frankenphp-y-caddy)
4. [PHP (`php.ini` y extensiones)](#4-php-phpini-y-extensiones)
5. [Variables de entorno (`.env*`)](#5-variables-de-entorno-env)
6. [Symfony (`config/`)](#6-symfony-config)
7. [Composer](#7-composer)
8. [Calidad: PHPUnit, PHPStan, deptrac, cs-fixer, Makefile, CI](#8-calidad-phpunit-phpstan-deptrac-cs-fixer-makefile-ci)
9. [Observabilidad](#9-observabilidad)
10. [Lo que ya está bien (no tocar)](#10-lo-que-ya-está-bien-no-tocar)
11. [Ficheros propuestos completos](#11-ficheros-propuestos-completos)
12. [Orden de implantación](#12-orden-de-implantación)

---

## 0. Resumen y prioridades

| # | Área | Problema | Impacto |
|---|------|----------|---------|
| 1.1 | Tests | `APP_ENV=test` acaba usando la `DATABASE_URL` real del contenedor (BD remota), no `*_test` | 🔴 Tests contra una BD real |
| 1.2 | BD dev | La BD de desarrollo es un Postgres en una **IP pública** por el 5432 | 🔴 Superficie de ataque, sin aislamiento |
| 1.3 | `.env` | Patrón `DB_*` → `DATABASE_URL` compuesto: los overrides de `DB_*` se ignoran | 🔴 Origen de 1.1 |
| 1.4 | Symfony | `X-Forwarded-Host` confiado sin `trusted_hosts` | 🟠 Host header injection |
| 2.1 | Docker | Dos Dockerfiles casi iguales, imagen prod ~860 MB con git/composer/herramientas | 🟠 Tamaño, superficie, deriva dev≠prod |
| 2.3 | Docker | Prod como root-capable (`setcap` :80), sin `no-new-privileges`, sin rotación de logs | 🟠 Hardening |
| 2.5 | Deploy | Migraciones en el entrypoint de cada contenedor | 🟠 Carreras con >1 réplica |
| 3.x | Caddy | Sin cabeceras de seguridad, access log de cada healthcheck, `Server: Caddy`, sin límite de body | 🟡 |
| 4.x | PHP | `post_max_size=50M` para una API JSON; workers × `memory_limit` = límite del contenedor | 🟡 |
| 6.1 | Symfony | Dos entornos de desarrollo (`local` y `dev`) duplicando config | 🟠 Deriva + bug de PHPStan |
| 6.2 | Symfony | Sesión activada en una API stateless | 🟡 |
| 6.6 | Messenger | Sin `doctrine_transaction` en el command bus, transporte/variable sin uso | 🟡 |
| 8.x | Calidad | Sin CI, PHPStan analiza `reference.php`, PHPUnit sin orden aleatorio | 🟠 |

---

## 1. 🔴 Críticos: base de datos y entornos

### 1.1 🔴 Los tests (`APP_ENV=test`) no usan la BD de test

**Qué hay.** `.env.test` define `DB_NAME=farmapremium_test` y vuelve a componer `DATABASE_URL` a partir de `DB_*`.
Pero `docker-compose.override.yml` carga `.env` y `.env.local` con `env_file`, así que el contenedor arranca con
`DATABASE_URL` **como variable de entorno real**. En Symfony las variables reales **siempre ganan** a los ficheros
`.env*`, de modo que `.env.test` no tiene efecto sobre `DATABASE_URL`.

Comprobado en el contenedor:

```
$ APP_ENV=test bin/console debug:container --env-var=DATABASE_URL
Processed value  "postgresql://admin:***@<IP pública>:5432/postgres?..."
```

Es decir: la suite funcional se ejecuta contra la base `postgres` del servidor remoto, no contra `*_test`.
DAMA hace rollback de cada test, pero `CREATE SCHEMA`, migraciones, `TRUNCATE`, secuencias, o cualquier test que
haga commit explícito, tocan la BD real.

**Propuesta (las tres, son complementarias):**

1. Activar el sufijo de test de la receta oficial de DoctrineBundle en `config/packages/doctrine.yaml`.
   Así, **aunque** `DATABASE_URL` venga del entorno, el nombre de BD en test siempre termina en `_test`
   (y soporta ParaTest con `TEST_TOKEN`):

   ```yaml
   when@test:
       doctrine:
           dbal:
               dbname_suffix: '_test%env(default::TEST_TOKEN)%'
   ```

2. Guard en `tests/bootstrap.php` que aborte si la BD no es de test (cinturón y tirantes; lo usan muchos equipos
   tras haberse llevado un susto):

   ```php
   $url = (string) ($_SERVER['DATABASE_URL'] ?? $_ENV['DATABASE_URL'] ?? '');
   $host = parse_url($url, PHP_URL_HOST);
   if (!in_array($host, ['database', 'db', 'localhost', '127.0.0.1'], true)) {
       fwrite(STDERR, "Refusing to run tests against non-local database host \"{$host}\".\n");
       exit(1);
   }
   ```

3. No inyectar `DATABASE_URL` en el contenedor de dev vía `env_file` para los tests: con un Postgres local en el
   compose (ver 1.2) la URL de `.env` apunta a `database` y `.env.test` puede fijar la suya.

**Por qué.** Es la regla número uno de cualquier proyecto serio: *los tests nunca pueden tener la capacidad de
conectarse a una BD que no sea desechable*. El `dbname_suffix` existe precisamente por esto y viene en la receta Flex.

### 1.2 🔴 BD de desarrollo expuesta en Internet

**Qué hay.** El entorno `local` se conecta a un Postgres en una IP pública por el puerto 5432, con el usuario `admin`
y la base `postgres`.

**Propuesta.**
- Añadir un servicio `database` (Postgres 18) al compose de desarrollo, con healthcheck y volumen nombrado
  (ver §11.2). Es lo que hacen `symfony-docker`, API Platform y prácticamente todos los proyectos Dockerizados:
  `docker compose up` debe levantar **todo** lo necesario, sin depender de recursos externos.
- En el servidor, Postgres **solo** en la red interna de Docker (`dokploy-network`), sin publicar el 5432. Si hace falta
  acceso puntual: túnel SSH (`ssh -L 5432:<host interno>:5432 server`), nunca puerto abierto.
- Usuario de aplicación con permisos mínimos (dueño del schema `tickets`), no un superusuario; y la base propia,
  no `postgres`.
- La contraseña actual contiene caracteres reservados de URL sin codificar (`@`, `&`, `*`). Funciona de casualidad
  según cómo parsee el driver; hay que URL-encodearla (ya lo documenta `.env.prod.example`, falta aplicarlo).

**Por qué.** Un Postgres en Internet recibe fuerza bruta continua; un `admin` con contraseña en una URL sin codificar
es una fuente de incidentes. Además, compartir BD entre dev y lo que sea que viva ahí rompe el aislamiento.

### 1.3 🔴 El patrón `DB_*` + `DATABASE_URL` compuesto

**Qué hay.** `.env`, `.env.example` y `.env.test` definen `DB_NAME`, `DB_USER`, … y luego
`DATABASE_URL="postgresql://${DB_USER}:${DB_PASSWORD}@..."`.

**Problema.** La interpolación se hace **en el fichero donde se define `DATABASE_URL`**. Si `DATABASE_URL` llega ya
resuelta como variable real (Docker `env_file`, Dokploy), cambiar `DB_NAME` en otro fichero no hace nada. Es justo lo
que provoca 1.1 y lo que ya ha dado guerra antes.

**Propuesta.** Una única variable: `DATABASE_URL`. Eliminar `DB_*` de todos los `.env*`. Si se quieren piezas
separadas por comodidad, que vivan solo en el compose del servicio `database`, no en Symfony.

**Por qué.** Es la convención de Symfony/Doctrine (una DSN) y la de las plataformas (Heroku, Platform.sh, Dokploy):
una sola fuente de verdad, sin interpolaciones encadenadas que dependen del orden de carga.

### 1.4 🟠 `trusted_hosts` y cabeceras de proxy

**Qué hay.** `framework.trusted_headers` incluye `x-forwarded-host`, `x-forwarded-port` y `x-forwarded-prefix`, y
`trusted_proxies` = rangos privados. No hay `trusted_hosts`.

**Propuesta.**
```yaml
framework:
    trusted_proxies: '%env(TRUSTED_PROXIES)%'
    trusted_headers: ['x-forwarded-for', 'x-forwarded-proto', 'x-forwarded-host']
    trusted_hosts: '%env(default::TRUSTED_HOSTS)%'   # prod: '^api\.tu-dominio\.com$'
```
- Quitar `x-forwarded-prefix` (no hay subruta) y `x-forwarded-port` (Traefik ya manda proto/host).
- `TRUSTED_HOSTS` en Dokploy con el dominio real.
- Unificar el valor: `.env` usa `127.0.0.1,PRIVATE_SUBNETS` y la imagen `private_ranges`. Symfony 7.4 acepta ambos
  (`Request::setTrustedProxies`), pero mejor uno solo (`private_ranges`, igual que Caddy).

**Por qué.** Confiar en `X-Forwarded-Host` sin `trusted_hosts` permite *host header injection* (enlaces absolutos,
reset de contraseñas, cache poisoning) si alguna petición llega sin pasar por Traefik. `trusted_hosts` es barato y es
parte de la checklist de despliegue de Symfony.

---

## 2. Docker y Compose

### 2.1 🟠 Un único Dockerfile multi-stage (dev y prod desde la misma base) → REVIEW §5.7

**Qué hay.** `etc/app/dev/Dockerfile` y `etc/app/prod/Dockerfile` repiten base, paquetes y extensiones. Prod
incluye `git`, `acl`, `file`, `gettext-base`, Composer y extensiones sin uso. Resultado ~860 MB.

**Propuesta.** Un `Dockerfile` con *targets* (patrón de `dunglas/symfony-docker`, el estándar de facto para
FrankenPHP + Symfony):

```
base     → frankenphp + extensiones de runtime + Caddyfile + entrypoint + healthcheck
├── dev  → + xdebug, composer, herramientas de shell, usuario con tu UID
├── build→ + composer, instala vendor --no-dev, dump-env, warmup de caché
└── prod → base + COPY --from=build /web  (sin composer, sin git, sin herramientas)
```

Ver el fichero completo en §11.1.

**Por qué.**
- **Paridad dev/prod** (12-factor §X): la misma base y las mismas extensiones; lo que funciona en local funciona en prod.
- **Imagen final mínima**: menos CVEs que parchear, pull más rápido en cada deploy, menos superficie si alguien
  consigue ejecutar código (sin `git`, sin `composer`, sin `curl` a ser posible).
- Un solo sitio para subir de versión de FrankenPHP/PHP.

### 2.2 🟡 Build reproducible y cacheado

- `ARG FRANKENPHP_VERSION` / `PHP_VERSION` en un solo sitio y, en prod, **fijar por digest**
  (`dunglas/frankenphp:1.13.0-php8.5@sha256:…`). El tag puede re-publicarse; el digest no.
- `# syntax=docker/dockerfile:1` + `RUN --mount=type=cache,target=/root/.composer/cache composer install …`:
  la caché de Composer sobrevive entre builds sin acabar en la imagen (hoy se usa `--no-cache`, que obliga a
  descargar todo cada vez).
- Composer en la etapa de build desde la imagen oficial, versión fijada:
  `COPY --from=composer/composer:2.8-bin /composer /usr/local/bin/composer`.
- **Renovate o Dependabot** (`docker`, `composer`, `github-actions`) para que las subidas de versión lleguen como PR.

**Por qué.** Builds deterministas y rápidos; las actualizaciones de base (parches de seguridad de PHP/Caddy) dejan de
depender de acordarse.

### 2.3 🟠 Hardening del contenedor de producción

**Qué hay.** El proceso corre como `www-data` (bien), pero escucha en `:80` gracias a `setcap CAP_NET_BIND_SERVICE`
sobre el binario. No hay `no-new-privileges`, ni `cap_drop`, ni rotación de logs.

**Propuesta.**
- Escuchar en un puerto no privilegiado (`SERVER_NAME=:8080`) y **quitar el `setcap`**. En Dokploy, el dominio apunta
  al puerto 8080. Traefik es quien expone 80/443 al mundo.
- En `docker-compose` de prod:
  ```yaml
  security_opt: ["no-new-privileges:true"]
  cap_drop: [ALL]
  stop_grace_period: 30s
  logging:
    driver: json-file
    options: { max-size: "10m", max-file: "5" }
  ```
- 🟢 Avanzado: `read_only: true` + `tmpfs` para `/tmp`. Requiere que `var/cache/prod` esté *warm* en la imagen (ya lo
  está) y que los pools de caché vayan a APCu/Redis en vez de a disco; dejarlo para cuando haya Redis.

**Por qué.**
- `no-new-privileges` **anula las file capabilities** (`setcap`): por eso hay que pasar a 8080 antes de activarlo.
  Juntos, un proceso comprometido no puede escalar privilegios ni abrir puertos bajos. Es lo que piden CIS Docker
  Benchmark y los escáneres (Trivy, Docker Scout).
- Sin `max-size`, el driver `json-file` crece sin límite y acaba llenando el disco del servidor (incidente clásico).
- `stop_grace_period` deja a FrankenPHP terminar las peticiones en curso al redeployar.

### 2.4 🟡 Estructura de ficheros Compose

**Qué hay.** `docker-compose.yml` es directamente **prod** y `docker-compose.override.yml` (commiteado) lo convierte en
dev. Funciona porque Dokploy usa `-f docker-compose.yml`, pero cualquiera que haga `docker compose up` en el servidor
obtiene la imagen de **dev** con Xdebug y el código montado.

**Propuesta** (layout de `symfony-docker`):
```
compose.yaml            # común: servicio, red, healthcheck
compose.override.yaml   # dev (se carga solo): target dev, volúmenes, puertos, database
compose.prod.yaml       # prod: target prod, image:, hardening, logging
```
- Dokploy → "Compose path": `compose.yaml` + `compose.prod.yaml` (o `-f compose.yaml -f compose.prod.yaml`).
- Nombres `compose.yaml`: el nombre moderno que recomienda la spec de Compose.

**Por qué.** Que el comportamiento por defecto sea el seguro y explícito; prod nunca depende de que *no* exista un fichero.

### 2.5 🟠 Migraciones al arrancar el contenedor

**Qué hay.** El entrypoint espera a la BD, crea el schema y lanza `doctrine:migrations:migrate` si
`RUN_MIGRATIONS=true`.

**Problema.** Con más de una réplica (o durante un *rolling update*, donde conviven la versión vieja y la nueva) dos
contenedores pueden migrar a la vez. Además, si la migración falla, el contenedor entra en bucle de reinicios y el
deploy se queda a medias.

**Propuesta.**
- Lo profesional es un **paso de release separado**: Dokploy permite un comando *pre-deploy* / o en CI:
  `docker compose run --rm app-tickets bin/console doctrine:migrations:migrate --no-interaction`.
  Heroku lo llama `release` phase; Kubernetes, un `Job`/`initContainer`.
- Si se mantiene en el entrypoint (aceptable con 1 réplica), protegerlo con un *advisory lock* de Postgres
  (`SELECT pg_advisory_lock(…)`) o documentar explícitamente "solo 1 réplica".
- Mover `all_or_nothing: true` a `doctrine_migrations.yaml` (ver 6.5) para no depender del flag.

### 2.6 🟡 Construir en CI y desplegar una imagen etiquetada

**Qué hay.** `build:` en el compose de prod: Dokploy compila en el propio servidor en cada deploy.

**Propuesta.** CI construye `target: prod`, la etiqueta con el SHA de git (`ghcr.io/<user>/tickets-api:<sha>`) y la
publica en GHCR; Dokploy despliega `image: …:${IMAGE_TAG}`.

**Por qué.** *Build once, deploy many*: la imagen que pasó los tests es exactamente la que llega a prod; rollback =
cambiar el tag; el servidor no gasta CPU/RAM compilando ni necesita acceso a Packagist.

### 2.7 🟡 Entorno de desarrollo

| Qué hay | Propuesta | Por qué |
|---|---|---|
| Sin servicio de BD | `database` Postgres 18 con healthcheck + `depends_on: condition: service_healthy` (§11.2) | `docker compose up` autosuficiente; resuelve 1.1/1.2 |
| `USER_ID`/`GROUP_ID` sin default (→ REVIEW §5.9) | `${USER_ID:-1000}` | Build no rompe sin `.env.local` |
| El `Caddyfile` se copia en la imagen | Montarlo en dev: `./etc/caddy/Caddyfile:/etc/frankenphp/Caddyfile:ro` | Cambiar Caddy sin rebuild |
| Montaje `~/.ssh` completo | Reenviar el agente: `${SSH_AUTH_SOCK}:/ssh-agent` + `SSH_AUTH_SOCK=/ssh-agent` | No expone las claves privadas al contenedor |
| `.gitconfig` con `credential.helper = store` (→ REVIEW §6.4) | Quitarlo | Guarda tokens en claro |
| Healthcheck a `:2019/metrics` (API admin de Caddy) | `/health`, igual que prod | Comprueba la app, no solo Caddy; misma semántica en ambos entornos |
| `container_name` | (Descartado en REVIEW, se mantiene) | — |

### 2.8 🟡 `.dockerignore` → REVIEW §5.8

Añadir:
```
/bin/*
!/bin/console
.php-cs-fixer.cache
.php-cs-fixer.dist.php
.deptrac.cache
deptrac.yaml
phpstan*.neon
Makefile
compose*.yaml
Caddyfile
/public/docs/
/templates/**/*.md
```
**Por qué.** `.deptrac.cache` (1,4 MB) y `.php-cs-fixer.cache` (32 KB) acaban en el contexto de build; los proxies de
`bin/` de herramientas dev apuntan a `vendor/` que en prod no existe (binarios rotos dentro de la imagen). Menos
contexto = builds más rápidos y caché de capas más estable.

### 2.9 🟢 Healthcheck: liveness vs readiness

**Qué hay.** `/health` devuelve 200 sin tocar la BD. **Esto está bien** como *liveness*: si la BD cae, no conviene que
Docker reinicie la app en bucle.

**Propuesta opcional.** Añadir `/health/ready` que haga `SELECT 1` para monitorización externa (Uptime Kuma, Better
Stack…), sin usarlo como healthcheck de Docker. Limitar `/`, `/status`, `/health` a `GET|HEAD` (hoy aceptan `ANY`).

---

## 3. FrankenPHP y Caddy

### 3.1 🟡 Caddyfile duplicado → REVIEW §6.2

`./Caddyfile` es idéntico a `etc/caddy/Caddyfile` y no se usa. Borrarlo.

### 3.2 🟡 Workers: número, reciclado y memoria

**Qué hay.** `FRANKENPHP_WORKER_CONFIG="num 4"`, `memory_limit=256M`, límite del contenedor `1g`.

**Propuesta.**
```caddyfile
frankenphp {
    num_threads {$FRANKENPHP_NUM_THREADS:8}
    max_threads auto
    worker {
        file /web/public/index.php
        num {$FRANKENPHP_NUM_WORKERS:4}
        {$FRANKENPHP_WORKER_CONFIG}
    }
}
```
- `FRANKENPHP_LOOP_MAX=500` en la imagen de prod: Symfony Runtime 7.4 lo lee (`SymfonyRuntime.php:163`) y recicla el
  worker cada N peticiones.
- Dimensionar: `num_workers × memory_limit` no debería superar el límite del contenedor. 4 × 256M = 1G = límite
  exacto; en la práctica una petición usa mucho menos, pero o se baja `memory_limit` a 128M (sobrado para esta API) o
  se sube el límite a 1.5G.

**Por qué.** En modo worker el proceso vive indefinidamente: cualquier fuga (un servicio con estado no reseteable,
un listener que acumula) crece sin límite. Reciclar cada N peticiones es la mitigación estándar (lo mismo que
`pm.max_requests` en PHP-FPM o `--max-requests` en Octane/RoadRunner).

### 3.3 🟡 Cabeceras de seguridad, `Server`, body y logs

Propuesta dentro del bloque del sitio:

```caddyfile
{$SERVER_NAME:localhost} {
    log {
        {$CADDY_SERVER_LOG_OPTIONS}
        format filter { … }   # como ahora
    }
    log_skip /health          # no registrar cada healthcheck (cada 30 s)

    request_body {
        max_size 2MB          # API JSON; coherente con post_max_size
    }

    header {
        -Server
        X-Content-Type-Options "nosniff"
        Referrer-Policy "no-referrer"
        X-Frame-Options "DENY"
        ?Permissions-Policy "browsing-topics=()"
    }
    …
}
```
- **HSTS** (`Strict-Transport-Security`) va en **Traefik**, que es quien termina TLS (o aquí si algún día Caddy sirve
  HTTPS directamente).
- No hace falta CSP en las respuestas JSON; sí en `/api/docs` y `/info` si se quieren endurecer (Scalar carga JS
  inline, probar antes).

**Por qué.** `nosniff` y `X-Frame-Options` son las cabeceras que exige cualquier pentest/escáner (OWASP Secure
Headers). Ocultar `Server` reduce *fingerprinting*. Limitar el body en el borde corta peticiones gigantes antes de
llegar a PHP. Sin `log_skip`, el 90 % del access log son healthchecks.

### 3.4 🟢 Métricas

Caddy/FrankenPHP exponen métricas Prometheus (`frankenphp_busy_workers`, `frankenphp_total_threads`, latencias…).
Activar `metrics` en las opciones globales y scrapear `:2019/metrics` **solo desde la red interna**. Útil para
dimensionar 3.2 con datos en vez de a ojo.

### 3.5 🟢 Opciones globales de Caddy en prod

- `admin localhost:2019` (explícito) o `admin off` si no se usan métricas.
- `servers { trusted_proxies static private_ranges; client_ip_headers X-Forwarded-For }` para que el access log
  registre la IP real del cliente y no la de Traefik.

---

## 4. PHP (`php.ini` y extensiones)

### 4.1 🟡 Extensiones

**Qué hay.** Se instalan `openssl curl json pdo mbstring opcache` (ya vienen en la imagen: son core o están
compiladas), y `amqp exif gd pcntl redis zip` que el código no usa. → REVIEW §5.7

**Propuesta.** En la base solo lo necesario: `apcu intl pdo_pgsql` (+ `xdebug` en dev).
- **Mantener `apcu`**: `cache.system` de Symfony la usa automáticamente si está disponible (metadatos de Doctrine,
  anotaciones/atributos, validator, serializer). En modo worker es una mejora real.
- `intl`: la usan Validator (`Email`, `Locale`…) y String; recomendable.
- PHP 8.5 trae OPcache siempre compilado: `install-php-extensions opcache` sobra.
- Declarar en `composer.json` lo que se usa (`ext-pdo_pgsql`, `ext-intl`, `ext-apcu` opcional) → ver 7.1.

**Por qué.** Cada extensión es código nativo con su propia superficie de CVEs y memoria por hilo (FrankenPHP es ZTS).

### 4.2 🟡 `php.ini` de producción

| Directiva | Ahora | Propuesta | Por qué |
|---|---|---|---|
| `post_max_size` / `upload_max_filesize` | 50M | 2M / 2M | API JSON sin subidas; coherente con `request_body max_size` |
| `memory_limit` | 256M | 128M (o ajustar límite del contenedor) | Ver 3.2 |
| `opcache.preload` | activo | Mantener o quitar (medir) | En modo worker el código ya está cargado en memoria; la ganancia del preload es marginal y complica (reinicio obligatorio). No es un error, pero no esperar mejoras |
| `opcache.jit` | — | `off` explícito (o `tracing` medido) | En APIs I/O bound el JIT apenas aporta; dejarlo explícito evita sorpresas entre versiones |
| `opcache.enable_cli` | — | `0` | Comandos de consola no necesitan OPcache compartido |
| `zend.exception_ignore_args` | (lo pone `php.ini-production`) | — | Ya OK: no se filtran argumentos en trazas |
| `realpath_cache_*` | OK | — | — |
| `apc.shm_size` | por defecto 32M | `64M` | Margen para `cache.system` |

### 4.3 🟡 `php.ini` de desarrollo

- `display_errors=Off` en dev es correcto para una API JSON (Symfony renderiza el error), pero `display_startup_errors=On`
  queda incoherente: dejar ambos alineados.
- `max_execution_time = 60`: subirlo a `0` en dev evita que una sesión larga de Xdebug corte la petición.
- 🟢 Separar un `00-base.ini` común (timezone, error_log, realpath) + `10-dev.ini`/`10-prod.ini`, para no repetir.

---

## 5. Variables de entorno (`.env*`)

### 5.1 🟠 Estrategia de ficheros → REVIEW §5.6

**Qué hay.** `.env` está en `.gitignore`; existen `.env.example`, `.env.local.example`, `.env.prod.example` y
`.env.test` (commiteado). La imagen prod sobrescribe `.env` con `APP_ENV=prod`.

**Propuesta (convención Symfony):**
| Fichero | Commit | Contenido |
|---|---|---|
| `.env` | ✅ | Defaults **no secretos** que permiten arrancar dev: `DATABASE_URL=postgresql://app:app@database:5432/tickets?serverVersion=18&charset=utf8`, `APP_SECRET=` vacío o de dev |
| `.env.local` | ❌ | Tus overrides (UID, Xdebug, etc.) |
| `.env.test` | ✅ | `KERNEL_CLASS`, `APP_SECRET` de test (la BD sale del sufijo `_test`) |
| `.env.prod.example` | ✅ | Plantilla para Dokploy (ya está bien hecha) |

Eliminar `.env.example` (pasa a ser `.env`) y renombrar `.env.local.example` → `.env.local.dist` o documentarlo en el README.

**Por qué.** Un clon nuevo debe poder hacer `make up && make test` sin copiar ficheros. Los secretos reales nunca van en
`.env` sino en `.env.local` (dev) o variables del orquestador (prod).

### 5.2 🟡 Limpieza de variables

- `MESSENGER_TRANSPORT_DSN`: definida pero ningún transporte la usa → quitarla (o configurar async, ver 6.6).
- `VAR_DUMPER_SERVER`: **usada** en `debug.yaml` pero **no definida** en ningún `.env` → `dump()` lanza
  `EnvNotFoundException`. Definir `VAR_DUMPER_SERVER=127.0.0.1:9912` en `.env` o usar
  `%env(default:var_dumper_default:VAR_DUMPER_SERVER)%`.
- `.env.test`: `farmapremium_test` es un resto de otro proyecto.
- `APP_DEBUG=0` con `APP_ENV=dev` en `.env.example`: combinación rara; dev = debug 1.
- `HTTP_WEB_URL` se usa solo para `DEFAULT_URI`; bien, pero documentarlo.

### 5.3 🟢 Secretos

En prod, variables de entorno de Dokploy es una opción válida. Alternativa profesional: **Symfony Secrets Vault**
(`config/secrets/prod/*.php` cifrados en el repo, solo la clave privada `SYMFONY_DECRYPTION_SECRET` en Dokploy).
Ventaja: los secretos se versionan y revisan en PR sin exponerse. El `.gitignore` ya excluye
`prod.decrypt.private.php`, así que el terreno está preparado.

---

## 6. Symfony (`config/`)

### 6.1 🟠 Dos entornos de desarrollo: `local` y `dev`

**Qué hay.** `local` (el que usas en Docker) y `dev` conviven, con bloques duplicados en `debug.yaml`,
`web_profiler.yaml`, `routes/framework.yaml`, `routes/web_profiler.yaml`, `monolog.yaml` (ancla) y `bundles.php`.
`make phpstan` calienta `local` pero `phpstan.dist.neon` lee `…DevDebugContainer.xml` (→ REVIEW §5.1).

**Propuesta.** Quedarse con los tres entornos estándar **`dev`, `test`, `prod`**.
- Si `dev` era para un servidor compartido/staging: ese servidor debe correr `APP_ENV=prod` con otras variables
  (otra BD, otro dominio). Staging **debe** ser prod en todo salvo datos, si no, no valida nada.
- Al quitar `local` desaparecen todas las duplicaciones y el bug de PHPStan.

**Por qué.** Todas las recetas Flex, bundles y documentación asumen `dev/test/prod`. Un entorno extra obliga a copiar
cada bloque `when@dev` y es fácil que diverjan (ya pasó con monolog: REVIEW §1.5).

### 6.2 🟡 `framework.yaml`

```yaml
framework:
    secret: '%env(APP_SECRET)%'
    trusted_proxies: '%env(TRUSTED_PROXIES)%'
    trusted_headers: ['x-forwarded-for', 'x-forwarded-proto', 'x-forwarded-host']
    trusted_hosts: '%env(default::TRUSTED_HOSTS)%'
    session: false          # API stateless
    php_errors:
        log: true

when@test:
    framework:
        test: true
```
- **`session: false`**: hoy la sesión está activa (`debug:config` muestra `enabled: true`). Una API REST stateless no
  la necesita; desactivarla quita el listener de sesión de cada petición y evita cookies accidentales.
  (El profiler no la necesita; `/info` tampoco.)
- Comentario en español → inglés (→ REVIEW §6.3).

### 6.3 🟡 `cache.yaml`

- Fijar `prefix_seed: ivandmn/tickets-api`. **Por qué:** el namespace de las claves se calcula a partir de la ruta del
  proyecto si no hay seed; con Redis compartido o al cambiar de ruta de despliegue, las claves cambian y la caché se
  invalida o colisiona.
- Quitar el bloque de comentarios de la receta (todo el fichero es comentario).
- Cuando haya Redis: `app: cache.adapter.redis` + `default_redis_provider: '%env(REDIS_URL)%'`.

### 6.4 🟡 `doctrine.yaml`

- `when@test` → `dbname_suffix` (ver 1.1). Hoy es un bloque vacío con un comentario.
- `identity_generation_preferences`: las PK son UUID, no hay secuencias; sobra.
- `server_version`: ya va en la URL. Alternativa más robusta: `server_version: '18'` en el YAML y quitarlo de la URL
  (así no depende de que cada entorno lo ponga bien).
- `result_cache_driver` en prod: solo tiene sentido si algún repositorio usa `->enableResultCache()`. Si no, quitarlo.
  `query_cache_driver` sí (cachea el DQL parseado).
- `schema_filter: ~^tickets\.~` y `validate_xml_mapping: true`: 👍.
- 🟢 Añadir en CI `doctrine:schema:validate --skip-sync` y, contra la BD de test migrada, `doctrine:schema:validate`
  para detectar desajustes entre mapping y migraciones.

### 6.5 🟡 `doctrine_migrations.yaml`

```yaml
doctrine_migrations:
    migrations_paths:
        'App\Infrastructure\Persistence\Doctrine\Migration': '%kernel.project_dir%/src/Infrastructure/Persistence/Doctrine/Migration'
    storage:
        table_storage:
            table_name: 'tickets.doctrine_migration_versions'
    all_or_nothing: true
    transactional: true
    enable_profiler: false
```
**Por qué.** `all_or_nothing` en config hace que *cualquier* ejecución (Makefile, entrypoint, CI, a mano) sea
atómica, no solo la que recuerda el flag. Postgres soporta DDL transaccional, así que sale gratis.
Indentación del fichero mezcla 2 y 6 espacios: normalizar.

### 6.6 🟡 `messenger.yaml`

- **`doctrine_transaction` en el `command.bus`**: cada comando se ejecuta en una transacción (commit/rollback
  automático). Es el patrón estándar en CQRS con Messenger: el handler no gestiona transacciones y nunca quedan
  escrituras a medias.
  ```yaml
  command.bus:
      middleware:
          - validation
          - doctrine_ping_connection
          - doctrine_transaction
  ```
- `doctrine_ping_connection`: **mantener** — en modo worker la conexión vive entre peticiones y Postgres puede
  cerrarla por inactividad. En `query.bus`/`event.bus` basta con tenerlo en el bus que entra primero; no hace daño.
- Quitar el transporte `sync://` (los mensajes sin `routing` ya se procesan síncronos) y los comentarios de la receta.
- Si en el futuro hay eventos asíncronos: transporte `async` + `failure_transport: failed` + worker
  `messenger:consume` como servicio aparte en compose, con `--time-limit`/`--memory-limit`.

### 6.7 🟡 `services.yaml`

```yaml
services:
    _defaults:
        autowire: true
        autoconfigure: true

    _instanceof:
        App\Application\Command\CommandHandlerInterface:
            tags: [{ name: messenger.message_handler, bus: command.bus }]
        App\Application\Query\QueryHandlerInterface:
            tags: [{ name: messenger.message_handler, bus: query.bus }]
        App\Application\Event\EventHandlerInterface:
            tags: [{ name: messenger.message_handler, bus: event.bus }]

    App\:
        resource: '../src/'
        exclude: [ … ]

    App\Application\Command\CommandBusInterface: '@App\Infrastructure\Bus\Symfony\MessengerCommandBus'
    App\Application\Query\QueryBusInterface: '@App\Infrastructure\Bus\Symfony\MessengerQueryBus'
    App\Application\Event\EventBusInterface: '@App\Infrastructure\Bus\Symfony\MessengerEventBus'
    App\Application\Service\LoggerInterface: '@App\Infrastructure\Service\MonologLogger'
    App\Domain\Repository\TaskRepositoryInterface: '@App\Infrastructure\Persistence\Doctrine\Repository\DoctrineTaskRepository'
    App\Domain\Repository\UserRepositoryInterface: '@App\Infrastructure\Persistence\Doctrine\Repository\DoctrineUserRepository'
```
- **`_instanceof` por interfaz** en vez de tres bloques `resource` con `public: true` + `autoconfigure: false`: la regla
  vive en el tipo (el handler implementa la interfaz → queda registrado en su bus), sin globs que dependan del sufijo
  del nombre de fichero, y los handlers dejan de ser **públicos** (el contenedor puede inlinearlos y eliminarlos si no
  se usan; los servicios públicos solo deberían serlo si se obtienen con `$container->get()`).
- **Argumentos de los buses sobran**: Symfony registra *named autowiring* por bus
  (`FrameworkExtension::registerAliasForArgument`), así que `MessageBusInterface $commandBus` recibe `command.bus`
  sin configurar nada.
- Indentación inconsistente (2/4/6 espacios, comentario huérfano en columna 2): normalizar a 4.

### 6.8 🟡 `monolog.yaml`

- **Correlación**: añadir un `request_id` a cada log (de `X-Request-Id` que pone Traefik, o generado). Con un
  processor propio o `Monolog\Processor\UidProcessor`. **Por qué:** en prod con JSON a stderr, sin ID no hay forma de
  agrupar las líneas de una misma petición.
- `formatter: monolog.formatter.json` → definir un formatter JSON con `include_stacktraces: true` para que los errores
  lleguen con traza completa al agregador.
- Dev: además de ficheros, un handler a `php://stderr` (nivel `info`) para ver logs con `docker compose logs`; los
  ficheros en `var/log` crecen sin rotación (añadir `type: rotating_file, max_files: 7` o confiar en `make purge`).
- Prod: está bien planteado (fingers_crossed + canal `app` directo + deprecations aparte). 👍

### 6.9 🟡 `nelmio_api_doc.yaml`

- Decidir si `/api/docs` y `/api/docs.json` son públicos en prod. Si la API no es pública, limitarlos a
  `when@dev` o protegerlos como `/info`. **Por qué:** documentación expuesta = mapa de la superficie de ataque.
- `version: 1.0.0` fijo: leerlo de un parámetro (`%env(default:app_version:APP_VERSION)%`) que CI rellene con el tag.
- Comentarios en español → inglés.

### 6.10 🟢 Resto

| Fichero | Estado | Nota |
|---|---|---|
| `routing.yaml` | OK | `default_uri` + `strict_requirements: null` en prod = receta |
| `twig.yaml` | OK | `strict_variables` en test 👍 |
| `validator.yaml` | OK | Quitar comentarios de `auto_mapping` |
| `property_info.yaml` | OK | `with_constructor_extractor` 👍 |
| `debug.yaml` | ⚠️ | Ver 5.2 (`VAR_DUMPER_SERVER`) |
| `web_profiler.yaml` | ⚠️ | Duplicado local/dev (6.1); indentación de 2 y 4 espacios |
| `dama_doctrine_test_bundle.yaml` | OK | |
| `bundles.php` | 🟡 | `DoctrineFixturesBundle` con `AppFixtures` vacío: o se rellena o se quita |
| `StatusController` | 🟡 | Usa `Symfony\Component\Routing\Annotation\Route` (deprecado, eliminado en Symfony 8): cambiar a `Attribute\Route` |
| `config/reference.php` | OK | Generado por Symfony 7.4; excluirlo de PHPStan (8.2) |

---

## 7. Composer

### 7.1 🟡 `composer.json` → REVIEW §5.4

```json
{
    "name": "ivandmn/tickets-api",
    "description": "Ticket management REST API (hexagonal architecture)",
    "require": {
        "php": "~8.5.0",
        "ext-ctype": "*",
        "ext-iconv": "*",
        "ext-intl": "*",
        "ext-pdo_pgsql": "*",
        …
        "twig/twig": "^3.30"
    },
    "config": {
        "platform": { "php": "8.5.11" },
        "audit": { "abandoned": "fail" },
        "allow-plugins": { … },
        "optimize-autoloader": true,
        "classmap-authoritative": false,
        "sort-packages": true,
        "bump-after-update": true
    },
    "scripts": {
        "cs": "php-cs-fixer fix --dry-run --diff",
        "phpstan": "phpstan analyse",
        "deptrac": "deptrac analyse --fail-on-uncovered --report-uncovered",
        "test": "phpunit",
        "ci": ["@cs", "@deptrac", "@phpstan", "@test"]
    }
}
```
- `name`/`description`/`keywords`: siguen siendo los del "task manager".
- `"php": ">=8.5"` acepta PHP 9 → `~8.5.0`. `config.platform.php` hace que `composer update` resuelva para la versión
  de **producción** aunque tu host tenga otra.
- `ext-pdo_pgsql`, `ext-intl`: si falta la extensión en la imagen, falla el build (`check-platform-reqs`), no el
  runtime.
- Quitar `guzzlehttp/guzzle`, `predis/predis`, `twig/extra-bundle` (sin uso); `twig/twig "^2.12|^3.30"` → `^3.30`.
- `audit.abandoned: fail`: CI falla si dependes de un paquete abandonado.
- `scripts`: los comandos de calidad viven en Composer → CI no depende del Makefile ni de Docker.

### 7.2 🟢 Dependencias de desarrollo que suelen tener los proyectos profesionales

| Paquete | Para qué |
|---|---|
| `roave/security-advisories:dev-latest` | Impide *instalar* versiones con CVE conocidas (complementa `composer audit`) |
| `phpstan/phpstan-strict-rules`, `phpstan/phpstan-phpunit` | Reglas más estrictas y comprensión de asserts/mocks |
| `rector/rector` | Upgrades automáticos (Symfony 8, PHP 8.6) y refactors |
| `ergebnis/composer-normalize` | `composer.json` siempre ordenado/normalizado |
| `infection/infection` | Mutation testing sobre Domain/Application (mide calidad real de los tests) |

---

## 8. Calidad: PHPUnit, PHPStan, deptrac, cs-fixer, Makefile, CI

### 8.1 🟡 PHPUnit (`phpunit.dist.xml`)

- Añadir `executionOrder="random"` (detecta dependencias ocultas entre tests), `failOnRisky="true"`,
  `beStrictAboutOutputDuringTests="true"`, `displayDetailsOnTestsThatTriggerDeprecations="true"`.
- `<exclude><file>src/autoload.php</file>` no existe → quitar.
- Separar suites: `unit` (sin kernel), `integration` (repositorios contra BD), `functional` (HTTP). La extensión DAMA
  solo hace falta en las dos últimas.
- `phpunit.xml` (local, ignorado) está **desfasado** respecto al `.dist` (le faltan triggers de deprecations de
  Doctrine): borrarlo y usar el dist, o regenerarlo.
- `cacheDirectory="var/phpunit/.phpunit.result.cache"`: es un directorio, el nombre confunde → `var/cache/phpunit`.

### 8.2 🟡 PHPStan (`phpstan.dist.neon`) → REVIEW §5.1

```neon
parameters:
    level: 10
    tmpDir: var/cache/phpstan
    paths: [bin/, config/, public/, src/, tests/]
    excludePaths:
        - config/reference.php
    symfony:
        containerXmlPath: var/cache/dev/App_Infrastructure_Symfony_KernelDevDebugContainer.xml
```
- `config/reference.php` (1.374 líneas generadas) no aporta nada al análisis.
- `tmpDir` dentro de `var/` para que la caché persista en el volumen y se ignore en git.
- Level 10 👍. Añadir strict-rules (7.2).

### 8.3 🟡 deptrac → REVIEW §5.2

Restos de otro proyecto: `Farmapremium`, `Google|Firebase`, y un `skip_violations` sobre `EmitTestApplePassCli`, que no
existe. Con `--report-uncovered` estas entradas confunden. Añadir `cache_file: var/cache/deptrac.cache` para sacar la
caché de la raíz.

### 8.4 🟢 php-cs-fixer

Configuración muy completa (`@Symfony`, `@Symfony:risky`, paralelo). Añadir `->setCacheFile(__DIR__.'/var/cache/.php-cs-fixer.cache')`
para no ensuciar la raíz.

### 8.5 🟡 Makefile → REVIEW §5.5

- `.PHONY` para todos los targets.
- Prefijo para ejecutar dentro del contenedor desde el host:
  ```make
  DC   = docker compose
  EXEC = $(if $(wildcard /.dockerenv),,$(DC) exec -T app-tickets)
  PHP  = $(EXEC) php
  cache-clear: ; $(PHP) bin/console cache:clear
  ```
  (Funciona igual dentro y fuera del contenedor.)
- Quitar `chmod -R 777` (`fix-perms`): con el UID mapeado no hace falta; 777 nunca es la solución.
- `restart` usa `--profile supervisor` inexistente.
- Faltan: `test`, `test-functional`, `db-test` (crear `tickets_test` + migrar), `ci`.
- `build` con `--no-cache` siempre: lento; dejar `build` normal y `build-fresh` para el `--no-cache`.

### 8.6 🟠 CI (GitHub Actions) → REVIEW §5.3

Pipeline mínimo profesional:

```yaml
name: CI
on: [push, pull_request]
jobs:
  quality:
    runs-on: ubuntu-latest
    services:
      postgres:
        image: postgres:18-alpine
        env: { POSTGRES_USER: app, POSTGRES_PASSWORD: app, POSTGRES_DB: tickets_test }
        ports: ["5432:5432"]
        options: --health-cmd "pg_isready -U app" --health-interval 5s --health-retries 10
    env:
      DATABASE_URL: postgresql://app:app@127.0.0.1:5432/tickets?serverVersion=18&charset=utf8
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.5', extensions: intl, pdo_pgsql, apcu, coverage: none }
      - run: composer validate --strict
      - run: composer install --prefer-dist --no-progress
      - run: composer audit
      - run: composer cs
      - run: composer deptrac
      - run: bin/console cache:warmup --env=dev && composer phpstan
      - run: bin/console doctrine:database:create --env=test --if-not-exists
      - run: bin/console doctrine:migrations:migrate --env=test -n
      - run: composer test
  docker:
    needs: quality
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: docker/setup-buildx-action@v3
      - uses: docker/build-push-action@v6
        with: { target: prod, push: false, cache-from: type=gha, cache-to: type=gha,mode=max }
      # 🟢 + escaneo: aquasecurity/trivy-action sobre la imagen
```
(`--env=test` + `dbname_suffix` → la BD real es `tickets_test`.)

---

## 9. Observabilidad

| Qué | Propuesta | Por qué |
|---|---|---|
| Errores | Sentry (`sentry/sentry-symfony`) con `SENTRY_DSN` solo en prod | Agrupa excepciones, alerta, muestra request/usuario; el log a stderr no avisa a nadie |
| Logs | JSON a stderr ✅ + `request_id` (6.8) | Agregables en Loki/Better Stack/Datadog |
| Métricas | FrankenPHP/Caddy Prometheus (3.4) | Saturación de workers, latencia |
| Uptime | Monitor externo contra `/health` (y `/health/ready`) | Enterarte antes que los usuarios |
| Versión | `APP_VERSION` (SHA de git) como `ARG`/`ENV` en la imagen y en `/info`, logs y Sentry `release` | Saber qué build está desplegada |

---

## 10. Lo que ya está bien (no tocar)

- FrankenPHP en **modo worker** con Symfony Runtime 7.4 nativo y `watch` en dev: configuración moderna y correcta.
- Prod: `php.ini-production` + `opcache.validate_timestamps=0`, `classmap-authoritative`, `dump-env prod`, caché
  calentada en build, usuario `www-data`, healthcheck que pasa por toda la app.
- Logs a `stderr` en JSON en prod; canal `app` fuera del `fingers_crossed`.
- Redacción de `Authorization` en el access log de Caddy.
- `.env.prod.example` claro y documentado; `/info` protegido con Basic Auth + `hash_equals` y 404 sin contraseña.
- Schema propio `tickets` con `schema_filter` y tabla de migraciones dentro del schema.
- PHPStan level 10, deptrac con capas de vendor, cs-fixer `@Symfony:risky`, `failOnDeprecation` en PHPUnit.
- Imagen base fijada a versión concreta (`1.13.0-php8.5`), puerto de dev ligado a `127.0.0.1`.
- `.gitattributes` con `eol=lf` y `.editorconfig`.

---

## 11. Ficheros propuestos completos

### 11.1 `Dockerfile` (raíz, multi-stage)

```dockerfile
# syntax=docker/dockerfile:1

ARG FRANKENPHP_VERSION=1.13.0
ARG PHP_VERSION=8.5

########################################
# base: runtime común a dev y prod
########################################
FROM dunglas/frankenphp:${FRANKENPHP_VERSION}-php${PHP_VERSION} AS base

WORKDIR /web
ENV PHP_INI_SCAN_DIR=":$PHP_INI_DIR/app.conf.d"

RUN apt-get update \
    && apt-get install -y --no-install-recommends curl \
    && rm -rf /var/lib/apt/lists/*

RUN install-php-extensions apcu intl pdo_pgsql

COPY etc/app/php/00-base.ini $PHP_INI_DIR/app.conf.d/00-base.ini
COPY etc/caddy/Caddyfile /etc/frankenphp/Caddyfile
COPY --chmod=755 etc/app/docker-entrypoint.sh /usr/local/bin/docker-entrypoint

ENV SERVER_NAME=:8080
EXPOSE 8080

HEALTHCHECK --start-period=30s --interval=30s --timeout=5s --retries=3 \
    CMD curl -fsS -o /dev/null http://127.0.0.1:8080/health || exit 1

ENTRYPOINT ["docker-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]

########################################
# dev
########################################
FROM base AS dev

ARG USER=app
ARG USER_ID=1000
ARG GROUP_ID=1000

ENV APP_ENV=dev \
    FRANKENPHP_WORKER_CONFIG=watch \
    COMPOSER_MEMORY_LIMIT=-1

RUN apt-get update && apt-get install -y --no-install-recommends \
        git make unzip openssh-client bash-completion less micro bat jq lnav libxml2-utils \
    && ln -s /usr/bin/batcat /usr/local/bin/bat \
    && rm -rf /var/lib/apt/lists/*

RUN install-php-extensions xdebug @composer

RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
COPY etc/app/php/10-dev.ini $PHP_INI_DIR/app.conf.d/10-dev.ini

RUN (getent group ${GROUP_ID} || groupadd -g ${GROUP_ID} ${USER}) \
    && useradd --no-log-init -m -s /bin/bash -u ${USER_ID} -g ${GROUP_ID} ${USER} \
    && chown -R ${USER_ID}:${GROUP_ID} /web /config/caddy /data/caddy

COPY --chown=${USER_ID}:${GROUP_ID} etc/bash/.bashrc etc/bash/.bash_aliases /home/${USER}/
USER ${USER}

########################################
# build: vendor + caché de prod
########################################
FROM base AS build

COPY --from=composer/composer:2.8-bin /composer /usr/local/bin/composer
ENV APP_ENV=prod APP_DEBUG=0 COMPOSER_ALLOW_SUPERUSER=1

COPY composer.json composer.lock symfony.lock ./
RUN --mount=type=cache,target=/root/.composer/cache \
    composer install --no-dev --prefer-dist --no-autoloader --no-scripts --no-progress

COPY . ./
RUN echo 'APP_ENV=prod' > .env \
    && composer dump-autoload --classmap-authoritative --no-dev \
    && composer dump-env prod \
    && APP_SECRET=build DEFAULT_URI=http://localhost \
       DATABASE_URL="postgresql://build:build@localhost:5432/build?serverVersion=18&charset=utf8" \
       composer run-script --no-dev post-install-cmd \
    && rm -rf var/log/* \
    && chmod +x bin/console

########################################
# prod: imagen final mínima
########################################
FROM base AS prod

ARG APP_VERSION=dev
ENV APP_ENV=prod \
    APP_DEBUG=0 \
    APP_VERSION=${APP_VERSION} \
    TRUSTED_PROXIES=private_ranges \
    FRANKENPHP_WORKER_CONFIG="num 4" \
    FRANKENPHP_LOOP_MAX=500

RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY etc/app/php/10-prod.ini $PHP_INI_DIR/app.conf.d/10-prod.ini

COPY --from=build --chown=www-data:www-data /web /web
RUN chown -R www-data:www-data /config/caddy /data/caddy

USER www-data
```

> El entrypoint ya hace `composer install` solo cuando `APP_ENV != prod`, así que sirve para ambos targets.

### 11.2 `compose.yaml` + `compose.override.yaml` + `compose.prod.yaml`

```yaml
# compose.yaml — común
networks:
  default:
    name: ${EXTERNAL_NET:-app}
    external: true

services:
  app-tickets:
    container_name: app-tickets
    build:
      context: .
    restart: unless-stopped
```

```yaml
# compose.override.yaml — desarrollo (se carga automáticamente)
services:
  app-tickets:
    build:
      target: dev
      args:
        USER: ${USER:-app}
        USER_ID: ${USER_ID:-1000}
        GROUP_ID: ${GROUP_ID:-1000}
    restart: "no"
    env_file:
      - path: .env.local
        required: false
    environment:
      SSH_AUTH_SOCK: /ssh-agent
    ports:
      - "127.0.0.1:${HTTP_WEB_PORT:-8080}:8080"
    volumes:
      - ./:/web
      - ./etc/caddy/Caddyfile:/etc/frankenphp/Caddyfile:ro
      - ${SSH_AUTH_SOCK:-/dev/null}:/ssh-agent
    extra_hosts:
      - host.docker.internal:host-gateway
    depends_on:
      database:
        condition: service_healthy

  database:
    image: postgres:18-alpine
    environment:
      POSTGRES_DB: tickets
      POSTGRES_USER: app
      POSTGRES_PASSWORD: app
    healthcheck:
      test: ["CMD", "pg_isready", "-U", "app", "-d", "tickets"]
      interval: 5s
      timeout: 5s
      retries: 10
    volumes:
      - db_data:/var/lib/postgresql   # Postgres 18+: montar el padre, no /data
    ports:
      - "127.0.0.1:5432:5432"         # solo para tu IDE / cliente SQL

volumes:
  db_data:
```

```yaml
# compose.prod.yaml — Dokploy: -f compose.yaml -f compose.prod.yaml
services:
  app-tickets:
    image: ghcr.io/ivandmn/tickets-api:${IMAGE_TAG:-latest}
    build:
      target: prod
    env_file: .env
    stop_grace_period: 30s
    security_opt: ["no-new-privileges:true"]
    cap_drop: [ALL]
    logging:
      driver: json-file
      options: { max-size: "10m", max-file: "5" }
    deploy:
      resources:
        limits: { memory: 1g }
```

> Con `SERVER_NAME=:80` y TLS en Traefik, los volúmenes `caddy_data`/`caddy_config` de prod no guardan nada útil
> (no hay certificados que persistir): se pueden quitar.

### 11.3 `etc/caddy/Caddyfile`

```caddyfile
{
	skip_install_trust
	{$CADDY_GLOBAL_OPTIONS}

	servers {
		trusted_proxies static private_ranges
		client_ip_headers X-Forwarded-For
	}

	frankenphp {
		{$FRANKENPHP_CONFIG}
		worker {
			file /web/public/index.php
			{$FRANKENPHP_WORKER_CONFIG}
		}
	}
}

{$CADDY_EXTRA_CONFIG}

{$SERVER_NAME:localhost} {
	log {
		{$CADDY_SERVER_LOG_OPTIONS}
		format filter {
			request>uri query {
				replace authorization REDACTED
			}
			request>headers>Authorization delete
		}
	}
	log_skip /health

	root /web/public
	encode zstd br gzip

	request_body {
		max_size 2MB
	}

	header {
		-Server
		X-Content-Type-Options "nosniff"
		Referrer-Policy "no-referrer"
		X-Frame-Options "DENY"
		?Permissions-Policy "browsing-topics=()"
	}

	{$CADDY_SERVER_EXTRA_DIRECTIVES}

	@phpRoute not file {path}
	rewrite @phpRoute index.php

	@frontController path index.php
	php @frontController

	file_server {
		hide *.php
	}
}
```

---

## 12. Orden de implantación

Pensado para que cada paso sea un PR pequeño y verificable.

1. **🔴 Seguridad de datos (hoy):**
   - [ ] `dbname_suffix` en `when@test` + guard en `tests/bootstrap.php` (1.1)
   - [ ] Servicio `database` en compose de dev; `.env` apuntando a él (1.2, 2.7)
   - [ ] Solo `DATABASE_URL`, fuera `DB_*` (1.3)
   - [ ] Cerrar el 5432 público del servidor y cambiar la contraseña del usuario `admin` (1.2)
2. **🟠 Entornos y config Symfony:**
   - [ ] Unificar `local` → `dev` (6.1) — arregla también PHPStan
   - [ ] `trusted_hosts`, `trusted_headers`, `session: false` (1.4, 6.2)
   - [ ] `doctrine_transaction` en command bus, `all_or_nothing` (6.5, 6.6)
   - [ ] `services.yaml` con `_instanceof` (6.7)
   - [ ] `VAR_DUMPER_SERVER`, limpieza de variables y comentarios (5.2, 6.3, 6.10)
3. **🟠 CI:** composer scripts + GitHub Actions con Postgres (7.1, 8.6)
4. **🟠 Docker:**
   - [ ] Dockerfile multi-stage, extensiones mínimas, puerto 8080 (2.1, 4.1)
   - [ ] `compose.yaml` / `.override` / `.prod` + hardening + rotación de logs (2.3, 2.4)
   - [ ] Migraciones como paso de release (2.5)
   - [ ] `.dockerignore` (2.8)
5. **🟡 Runtime:** Caddyfile (3.x), `php.ini` (4.2/4.3), `FRANKENPHP_LOOP_MAX` y dimensionado (3.2)
6. **🟡 Calidad:** PHPUnit/PHPStan/deptrac/Makefile (8.1–8.5), Composer (7.1)
7. **🟢 Plataforma:** imagen en GHCR por SHA (2.6), Renovate (2.2), Sentry + métricas + `APP_VERSION` (9), Secrets Vault (5.3)
