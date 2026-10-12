# Guía: logs centralizados con OpenObserve + Vector en Docker

Montaje ligero de logs para un VPS con Docker (API, Postgres, Redis, RabbitMQ). Al terminar tendrás todos los logs de todos los contenedores centralizados, con búsqueda, filtros por campo, dashboards y alertas.

## Arquitectura

```
App / Postgres / Redis / RabbitMQ
        │  (stdout / stderr)
        ▼
Docker (driver "local", con rotación)
        │
        ▼
Vector (1 instancia por servidor, lee el socket de Docker)
        │  HTTP + buffer en disco
        ▼
OpenObserve (almacenamiento, búsqueda, dashboards, alertas)
```

- Los logs **no pasan por RabbitMQ**. Vector los envía directamente a OpenObserve por HTTP.
- Si OpenObserve se cae, Vector guarda los logs en un buffer en disco y los reenvía cuando vuelve.
- La app no sabe nada del sistema de logs: solo escribe por stdout.

---

## 1. Configurar el driver de logs de Docker

Por defecto Docker usa `json-file` sin rotación, que acaba llenando el disco. Cambiamos a `local` (comprimido y con rotación).

Edita (o crea) `/etc/docker/daemon.json`:

```json
{
  "log-driver": "local",
  "log-opts": {
    "max-size": "10m",
    "max-file": "3"
  }
}
```

Reinicia Docker:

```bash
sudo systemctl restart docker
```

> ⚠️ Solo se aplica a contenedores **nuevos**. Recrea los existentes con `docker compose up -d --force-recreate`.

---

## 2. Preparar la app para producción

Esto es lo que más mejora la calidad de los logs. Reglas básicas:

1. **Escribir en JSON por stdout**, una línea por evento. Nada de ficheros dentro del contenedor.
2. **Nivel de log por variable de entorno** (`LOG_LEVEL=info` en producción, `debug` solo puntualmente).
3. **Middleware de acceso**: un log por petición con método, ruta, status y duración.
4. **request_id**: generar uno por petición (o reutilizar la cabecera `X-Request-ID`), incluirlo en todos los logs de esa petición, devolverlo en la respuesta y propagarlo en los mensajes de RabbitMQ.
5. **Nunca loguear** contraseñas, tokens, cabeceras `Authorization` ni datos de tarjeta. Enmascarar datos personales (RGPD).

Ejemplo de línea de log:

```json
{"ts":"2026-10-12T10:31:07Z","level":"info","msg":"request completed","method":"POST","path":"/orders","status":201,"duration_ms":48,"request_id":"a1b2c3","user_id":"u_942"}
```

Librerías recomendadas según lenguaje: **pino** (Node), **structlog** o **loguru** (Python), **Serilog** (.NET), **slog** o **zap** (Go), **Logback** con encoder JSON (Java).

---

## 3. Variables de entorno

Crea un fichero `.env` junto a tu `docker-compose.yml`:

```env
O2_USER=admin@tudominio.com
O2_PASSWORD=una-contraseña-larga-y-segura
```

> Añade `.env` a tu `.gitignore`.

---

## 4. Añadir OpenObserve y Vector al `docker-compose.yml`

Añade estos servicios junto a los que ya tienes:

```yaml
services:
  # ... tus servicios actuales (api, postgres, redis, rabbitmq) ...

  openobserve:
    image: public.ecr.aws/zinclabs/openobserve:latest   # fija una versión cuando funcione
    restart: unless-stopped
    environment:
      ZO_ROOT_USER_EMAIL: ${O2_USER}
      ZO_ROOT_USER_PASSWORD: ${O2_PASSWORD}
      ZO_DATA_DIR: /data
      ZO_COMPACT_DATA_RETENTION_DAYS: "30"
      ZO_MEMORY_CACHE_MAX_SIZE: "256"   # MB de caché en memoria
    volumes:
      - o2data:/data
    ports:
      - "127.0.0.1:5080:5080"   # solo accesible desde el propio servidor
    mem_limit: 1g

  vector:
    image: timberio/vector:latest-alpine   # fija una versión cuando funcione
    restart: unless-stopped
    environment:
      O2_USER: ${O2_USER}
      O2_PASSWORD: ${O2_PASSWORD}
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock:ro
      - ./vector.yaml:/etc/vector/vector.yaml:ro
      - vectordata:/var/lib/vector   # buffer en disco
    depends_on:
      - openobserve
    mem_limit: 128m

volumes:
  o2data:
  vectordata:
```

Notas:

- **Vector se instala una sola vez por servidor.** Lee los logs de todos los contenedores a través del socket de Docker; no hace falta tocar los demás servicios.
- Si en el futuro tienes más servidores, pon un Vector en cada uno apuntando al mismo OpenObserve.

---

## 5. Configurar Vector

Crea `vector.yaml` junto al compose:

```yaml
sources:
  docker:
    type: docker_logs
    exclude_containers: ["openobserve"]   # opcional: evita ruido de OpenObserve

transforms:
  parse:
    type: remap
    inputs: [docker]
    source: |
      # Si el mensaje es JSON (tu API), convierte sus claves en campos reales
      parsed, err = parse_json(.message)
      if err == null && is_object(parsed) {
        . = merge(., object!(parsed))
      }
      # Nombre del servicio del compose, o del contenedor si no lo hay
      .service = .label."com.docker.compose.service" ?? .container_name
      del(.label)

sinks:
  openobserve:
    type: http
    inputs: [parse]
    uri: "http://openobserve:5080/api/default/docker/_json"
    method: post
    auth:
      strategy: basic
      user: "${O2_USER}"
      password: "${O2_PASSWORD}"
    compression: gzip
    encoding:
      codec: json
      timestamp_format: rfc3339
    healthcheck:
      enabled: false
    buffer:
      type: disk
      max_size: 268435488   # ~256 MB (mínimo permitido)
      when_full: drop_newest
```

Qué hace cada parte:

| Bloque | Función |
|---|---|
| `sources.docker` | Lee los logs de todos los contenedores |
| `transforms.parse` | Convierte el JSON de la app en campos filtrables y añade `service` |
| `sinks.openobserve` | Envía a OpenObserve, organización `default`, stream `docker` |
| `buffer` | Guarda en disco si OpenObserve no responde; nunca bloquea tus contenedores |

Los logs que no son JSON (Postgres, RabbitMQ, Redis) se quedan como texto en el campo `message`, sin romper nada.

**Alternativa a `exclude_containers`:** recoger solo los contenedores marcados. Añade `labels: ["logs=true"]` a los servicios que quieras y usa `include_labels: ["logs=true"]` en la fuente.

---

## 6. Arrancar

```bash
docker compose up -d
docker compose ps
```

Comprueba que Vector no da errores:

```bash
docker compose logs -f vector
```

---

## 7. Acceder a OpenObserve

El puerto solo escucha en localhost. Desde tu ordenador, abre un túnel SSH:

```bash
ssh -L 5080:localhost:5080 usuario@tu-vps
```

Y entra en <http://localhost:5080> con el usuario y contraseña del `.env`.

En **Logs**, selecciona el stream `docker`.

### Probar que llegan logs

Genera un log de prueba en el servidor:

```bash
docker run --rm alpine echo '{"level":"error","msg":"prueba de logs","service_test":"ok"}'
```

En unos segundos debería aparecer en OpenObserve con `level` y `msg` como campos.

### Consultas útiles (SQL)

Errores por servicio:

```sql
SELECT service, count(*) AS total
FROM docker
WHERE level = 'error'
GROUP BY service
```

Endpoints más lentos:

```sql
SELECT path, avg(duration_ms) AS media_ms, count(*) AS peticiones
FROM docker
WHERE duration_ms IS NOT NULL
GROUP BY path
ORDER BY media_ms DESC
LIMIT 10
```

Todo lo ocurrido en una petición concreta:

```sql
SELECT * FROM docker WHERE request_id = 'a1b2c3'
```

---

## 8. Alertas y dashboards

Configúralo desde el primer día:

- **Alertas** (sección *Alerts*): por ejemplo, "más de 10 errores en 5 minutos en el servicio `api`". Destinos: Slack, email o webhook.
- **Dashboard** básico con errores por servicio, peticiones por status y latencia media de la API (si logueas `duration_ms`).

---

## 9. Acceso por HTTPS (opcional)

Si prefieres no usar túnel SSH, ponlo detrás de tu reverse proxy con HTTPS. Ejemplo con Caddy en el host:

```
logs.tudominio.com {
    reverse_proxy localhost:5080
}
```

OpenObserve tiene login propio, pero **nunca lo expongas en HTTP plano**.

---

## 10. Mantenimiento

- **Fija versiones** de las imágenes (en vez de `latest`) una vez funcione, y actualiza de forma controlada.
- **Vigila el consumo** los primeros días con `docker stats` y ajusta `mem_limit` y `ZO_MEMORY_CACHE_MAX_SIZE`.
- **Vigila el disco** con `df -h` y `docker system df`. Ajusta `ZO_COMPACT_DATA_RETENTION_DAYS` según el espacio.
- **Backups**: los datos están en el volumen `o2data`. Si los logs son importantes para ti (auditoría, incidencias), inclúyelo en tus copias.
- **Errores de la app**: considera complementar los logs con un servicio de error tracking como Sentry para agrupar excepciones y recibir avisos.

---

## 11. Solución de problemas

| Síntoma | Qué revisar |
|---|---|
| No llega ningún log | `docker compose logs vector`. Comprueba que el socket está montado y que OpenObserve está levantado |
| Errores 401 en Vector | Usuario/contraseña del `.env` no coinciden con los de OpenObserve |
| Los campos JSON no aparecen separados | La app no escribe JSON válido en una sola línea, o escribe texto antes del JSON |
| Faltan logs de contenedores antiguos | Esos contenedores no se recrearon tras cambiar el driver (paso 1) |
| OpenObserve consume mucha RAM | Baja `ZO_MEMORY_CACHE_MAX_SIZE` y revisa el `mem_limit` |
| Disco lleno | Reduce la retención y revisa los límites `max-size`/`max-file` de Docker |

---

## Resumen de ficheros

```
proyecto/
├── docker-compose.yml   # con openobserve y vector añadidos
├── vector.yaml          # configuración de Vector
└── .env                 # credenciales (fuera de git)
```

Y en el servidor: `/etc/docker/daemon.json` con el driver `local`.
