# Gestión de excepciones en la API REST (Symfony)

> **Instrucciones para el agente**: este documento describe la arquitectura de gestión de errores que hay que implementar en este proyecto. Antes de escribir código, explora el proyecto (estructura de carpetas, namespaces, versión de Symfony, excepciones y listeners existentes, cómo se validan hoy las requests) y adapta los namespaces y rutas de este documento a los que ya existen. Sigue el plan de migración del final paso a paso y no cambies el comportamiento de endpoints que no estén afectados.

---

## 1. Principios (obligatorios)

1. **Todas las excepciones de negocio viven en el dominio.** Cada bounded context tiene sus excepciones en `Domain/Exception`. Las genéricas (valor obligatorio, formato inválido, fuera de rango…) viven en `Shared/Domain/Exception`.
2. **El dominio NO conoce HTTP.** En el dominio no puede aparecer: ningún status HTTP, ningún nombre de campo del payload, ningún mensaje pensado para el cliente, ninguna clase de `Symfony\Component\HttpFoundation` ni `HttpKernel`.
3. **Toda excepción de dominio tiene un `errorCode`.** Es un `string` en `UPPER_SNAKE_CASE`, prefijado por contexto (`ORDER_ALREADY_PAID`, `PAYMENT_DECLINED`), único en todo el proyecto y estable: nunca cambia de significado ni se reutiliza.
4. **El `errorCode` NO es el `code` nativo de la excepción.** Las excepciones mantienen la firma estándar de PHP (`message`, `code` entero, `previous`) y además añaden el `errorCode` string. El `code` entero no se usa para el mapeo.
5. **El mensaje de la excepción de dominio es interno** (para logs y depuración). El mensaje que ve el cliente lo decide el mapper.
6. **El mapeo se hace en infraestructura con un mapper.** El `ErrorCodeMapper` asocia cada `errorCode` con: **status HTTP**, **mensaje público** (admite `{placeholders}`) y, opcionalmente, **campo del payload**.
7. **El `ApiExceptionListener` es el único punto que construye respuestas de error.** Lee del mapper el status, el mensaje y el campo, y devuelve siempre Problem Details (RFC 9457) con `Content-Type: application/problem+json`.
8. **Todo `errorCode` debe estar mapeado.** Un test lo garantiza. Un código sin mapear en producción se trata como bug: responde 500 y se registra un warning.

```
Dominio                                Infraestructura
──────────────────────────────         ─────────────────────────────────────────
Excepción                               ErrorCodeMapper
  errorCode: 'ORDER_ALREADY_PAID'  ──►    'ORDER_ALREADY_PAID' => status 409
  message (interno, logs)                                         mensaje público
  params: ['orderId' => '...']                                    campo (opcional)
  code (int nativo), previous                      │
                                                   ▼
                                        ApiExceptionListener ──► respuesta JSON
```

---

## 2. Estructura de ficheros

Adaptar a la estructura real del proyecto:

```
src/
├── Shared/
│   ├── Domain/Exception/
│   │   ├── DomainException.php          # base de todas las excepciones de dominio
│   │   └── InvalidValue.php             # errores genéricos de valor
│   └── Infrastructure/
│       ├── Http/
│       │   ├── ApiExceptionListener.php
│       │   └── ErrorMapping/
│       │       ├── ErrorMapping.php
│       │       ├── ErrorMappingProvider.php
│       │       ├── ErrorCodeMapper.php
│       │       └── SharedErrorMappings.php
│       └── Validation/
│           ├── ValidVO.php
│           └── ValidVOValidator.php
└── Order/                               # un bounded context cualquiera
    ├── Domain/Exception/
    │   └── OrderAlreadyPaid.php
    └── Infrastructure/Http/
        └── OrderErrorMappings.php
```

---

## 3. Dominio

### 3.1 Excepción base

```php
<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

abstract class DomainException extends \DomainException
{
    /**
     * @param string                     $errorCode código de negocio estable (NO es el code nativo)
     * @param string                     $message   mensaje interno, para logs
     * @param array<string, scalar|null> $params    datos de contexto para construir el mensaje público
     */
    public function __construct(
        private readonly string $errorCode,
        string $message = '',
        private readonly array $params = [],
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    final public function errorCode(): string
    {
        return $this->errorCode;
    }

    /** @return array<string, scalar|null> */
    final public function params(): array
    {
        return $this->params;
    }
}
```

### 3.2 Excepciones genéricas de valor

Las lanzan los Value Objects. No llevan campo: el campo lo resuelve la validación en el borde (sección 4).

```php
<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

final class InvalidValue extends DomainException
{
    public const REQUIRED       = 'VALUE_REQUIRED';
    public const INVALID        = 'VALUE_INVALID';
    public const INVALID_FORMAT = 'VALUE_INVALID_FORMAT';
    public const OUT_OF_RANGE   = 'VALUE_OUT_OF_RANGE';
    public const TOO_SHORT      = 'VALUE_TOO_SHORT';
    public const TOO_LONG       = 'VALUE_TOO_LONG';

    public static function required(?\Throwable $previous = null): self
    {
        return new self(self::REQUIRED, 'Value is required', [], 0, $previous);
    }

    public static function invalidFormat(string $expected, ?\Throwable $previous = null): self
    {
        return new self(
            self::INVALID_FORMAT,
            sprintf('Invalid format, expected %s', $expected),
            ['expected' => $expected],
            0,
            $previous,
        );
    }

    public static function outOfRange(int|float $min, int|float $max, int|float $actual): self
    {
        return new self(
            self::OUT_OF_RANGE,
            sprintf('Value %s out of range [%s, %s]', $actual, $min, $max),
            ['min' => $min, 'max' => $max],
        );
    }

    public static function tooShort(int $min): self
    {
        return new self(self::TOO_SHORT, sprintf('Value shorter than %d', $min), ['min' => $min]);
    }

    public static function tooLong(int $max): self
    {
        return new self(self::TOO_LONG, sprintf('Value longer than %d', $max), ['max' => $max]);
    }
}
```

### 3.3 Excepciones específicas de un contexto

Una clase por regla de negocio. El código se declara en la constante `ERROR_CODE`.

```php
<?php

declare(strict_types=1);

namespace App\Order\Domain\Exception;

use App\Order\Domain\OrderId;
use App\Shared\Domain\Exception\DomainException;

final class OrderAlreadyPaid extends DomainException
{
    public const ERROR_CODE = 'ORDER_ALREADY_PAID';

    public function __construct(OrderId $orderId, ?\Throwable $previous = null)
    {
        parent::__construct(
            self::ERROR_CODE,
            sprintf('Order %s is already paid', $orderId->value),
            ['orderId' => $orderId->value],
            0,
            $previous,
        );
    }
}
```

Crear un código específico solo cuando el cliente necesite reaccionar de forma distinta. Si basta con un error genérico de valor, usar `InvalidValue`.

### 3.4 Value Objects

```php
final class Quantity
{
    public function __construct(public readonly int $value)
    {
        if ($value < 1 || $value > 999) {
            throw InvalidValue::outOfRange(1, 999, $value);
        }
    }
}
```

Al envolver excepciones de librerías o de infraestructura dentro del dominio, pasarlas siempre como `previous` para no perder la traza.

---

## 4. Validación en el borde: asociar errores de VO a campos del payload

Los DTOs de request solo validan estructura (tipos, obligatorios) con constraints nativas. Las reglas de valor se delegan en el VO con la constraint `ValidVO`, que construye el VO y, si falla, convierte la excepción de dominio en una violación ligada a la propiedad. Así la regla vive solo en el VO, el Validator pone el campo automáticamente (también en anidados con `#[Assert\Valid]`) y se devuelven todos los errores a la vez.

```php
<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Validation;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::IS_REPEATABLE)]
final class ValidVO extends Constraint
{
    /** @param class-string $class */
    public function __construct(
        public readonly string $class,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(null, $groups, $payload);
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Validation;

use App\Shared\Domain\Exception\DomainException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class ValidVOValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidVO) {
            throw new UnexpectedTypeException($constraint, ValidVO::class);
        }

        if ($value === null) {
            return; // de los nulos se encarga NotNull / NotBlank
        }

        try {
            new ($constraint->class)($value);
        } catch (DomainException $e) {
            $this->context->buildViolation($e->getMessage())
                ->setCode($e->errorCode())
                ->setCause($e) // IMPRESCINDIBLE: el listener lee errorCode y params desde aquí
                ->addViolation();
        }
    }
}
```

Uso en el DTO:

```php
final class CreateOrderRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[ValidVO(CustomerId::class)]
        public readonly string $customerId,

        #[Assert\NotNull]
        #[ValidVO(Quantity::class)]
        public readonly int $quantity,
    ) {}
}

#[Route('/orders', methods: ['POST'])]
public function __invoke(#[MapRequestPayload] CreateOrderRequest $request): JsonResponse
```

> **Nombres de campo**: el *property path* del Validator es el nombre de la propiedad PHP. Si el serializer usa un name converter (por ejemplo `snake_case`), convertir el path con el mismo `NameConverterInterface` en el listener para que el campo devuelto coincida con la clave del payload.

---

## 5. Infraestructura: el mapper

### 5.1 Entrada del mapper

```php
<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http\ErrorMapping;

final class ErrorMapping
{
    /**
     * @param int         $status  status HTTP
     * @param string      $message mensaje público; admite {placeholders} con los params de la excepción
     * @param string|null $field   campo del payload asociado, si el error pertenece a uno
     */
    public function __construct(
        public readonly int $status,
        public readonly string $message,
        public readonly ?string $field = null,
    ) {}
}
```

### 5.2 Proveedores por contexto

Cada bounded context registra sus mapeos en su propia clase, así el mapeo escala sin un fichero central gigante.

```php
<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http\ErrorMapping;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.error_mapping_provider')]
interface ErrorMappingProvider
{
    /** @return array<string, ErrorMapping> errorCode => mapping */
    public function mappings(): array;
}
```

```php
<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http\ErrorMapping;

use App\Shared\Domain\Exception\InvalidValue;

final class SharedErrorMappings implements ErrorMappingProvider
{
    public function mappings(): array
    {
        return [
            InvalidValue::REQUIRED       => new ErrorMapping(422, 'El campo es obligatorio'),
            InvalidValue::INVALID        => new ErrorMapping(422, 'El valor no es válido'),
            InvalidValue::INVALID_FORMAT => new ErrorMapping(422, 'Formato no válido, se esperaba {expected}'),
            InvalidValue::OUT_OF_RANGE   => new ErrorMapping(422, 'Debe estar entre {min} y {max}'),
            InvalidValue::TOO_SHORT      => new ErrorMapping(422, 'Debe tener al menos {min} caracteres'),
            InvalidValue::TOO_LONG       => new ErrorMapping(422, 'No puede superar {max} caracteres'),
        ];
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Http;

use App\Order\Domain\Exception\OrderAlreadyPaid;
use App\Order\Domain\Exception\OrderNotFound;
use App\Order\Domain\Exception\InsufficientStock;
use App\Shared\Infrastructure\Http\ErrorMapping\ErrorMapping;
use App\Shared\Infrastructure\Http\ErrorMapping\ErrorMappingProvider;

final class OrderErrorMappings implements ErrorMappingProvider
{
    public function mappings(): array
    {
        return [
            OrderNotFound::ERROR_CODE     => new ErrorMapping(404, 'El pedido {orderId} no existe'),
            OrderAlreadyPaid::ERROR_CODE  => new ErrorMapping(409, 'El pedido {orderId} ya está pagado'),
            InsufficientStock::ERROR_CODE => new ErrorMapping(422, 'No hay stock suficiente', 'quantity'),
        ];
    }
}
```

Guía de status: `404` recurso inexistente, `409` estado incompatible con la operación, `422` regla de negocio o valor inválido, `403` operación no permitida para el usuario.

### 5.3 El mapper

```php
<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http\ErrorMapping;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class ErrorCodeMapper
{
    /** @var array<string, ErrorMapping> */
    private array $mappings = [];

    /** @param iterable<ErrorMappingProvider> $providers */
    public function __construct(
        #[AutowireIterator('app.error_mapping_provider')] iterable $providers,
    ) {
        foreach ($providers as $provider) {
            foreach ($provider->mappings() as $errorCode => $mapping) {
                if (isset($this->mappings[$errorCode])) {
                    throw new \LogicException(sprintf('Error code "%s" is mapped twice', $errorCode));
                }
                $this->mappings[$errorCode] = $mapping;
            }
        }
    }

    public function find(string $errorCode): ?ErrorMapping
    {
        return $this->mappings[$errorCode] ?? null;
    }

    /** @return list<string> */
    public function mappedCodes(): array
    {
        return array_keys($this->mappings);
    }

    /** Sustituye {placeholders} del mensaje público por los params de la excepción. */
    public function render(ErrorMapping $mapping, array $params): string
    {
        $replacements = [];
        foreach ($params as $key => $value) {
            $replacements['{' . $key . '}'] = (string) $value;
        }

        return strtr($mapping->message, $replacements);
    }
}
```

> En Symfony < 6.4 usar `#[TaggedIterator('app.error_mapping_provider')]` en lugar de `#[AutowireIterator]`.

---

## 6. Infraestructura: el exception listener

Orden de resolución:

1. **Validación fallida** (`#[MapRequestPayload]` envuelve una `ValidationFailedException` en una `HttpException` 422): un error por violación. El código sale de la excepción de dominio guardada como *cause* o, para constraints nativas, de una tabla de equivalencias. El mensaje sale del mapper. El campo es el *property path*; si no hay, el del mapper.
2. **Excepción de dominio**: status, mensaje y campo desde el mapper. Si el código no está mapeado, 500 y warning en el log.
3. **`HttpExceptionInterface`** del framework (ruta inexistente, JSON malformado, método no permitido…): su propio status.
4. **Cualquier otra**: 500 genérico. Los detalles solo se exponen con `kernel.debug`.

```php
<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use App\Shared\Domain\Exception\DomainException;
use App\Shared\Domain\Exception\InvalidValue;
use App\Shared\Infrastructure\Http\ErrorMapping\ErrorCodeMapper;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Exception\ValidationFailedException;

#[AsEventListener(event: KernelEvents::EXCEPTION)]
final class ApiExceptionListener
{
    /** Códigos de constraints nativas de Symfony => errorCode genérico propio. */
    private const NATIVE_CODES = [
        Assert\NotBlank::IS_BLANK_ERROR       => InvalidValue::REQUIRED,
        Assert\NotNull::IS_NULL_ERROR         => InvalidValue::REQUIRED,
        Assert\Type::INVALID_TYPE_ERROR       => InvalidValue::INVALID_FORMAT,
        Assert\Length::TOO_SHORT_ERROR        => InvalidValue::TOO_SHORT,
        Assert\Length::TOO_LONG_ERROR         => InvalidValue::TOO_LONG,
        Assert\Uuid::INVALID_CHARACTERS_ERROR => InvalidValue::INVALID_FORMAT,
    ];

    public function __construct(
        private readonly ErrorCodeMapper $mapper,
        private readonly LoggerInterface $appLogger,
        #[Autowire('%kernel.debug%')] private readonly bool $debug,
    ) {}

    public function __invoke(ExceptionEvent $event): void
    {
        $e = $event->getThrowable();
        $validation = $this->validationFailure($e);

        $problem = match (true) {
            $validation !== null                 => $this->fromValidation($validation),
            $e instanceof DomainException        => $this->fromDomain($e),
            $e instanceof HttpExceptionInterface => $this->fromHttp($e),
            default                              => $this->internal($e),
        };

        $problem['traceId'] = $event->getRequest()->headers->get('X-Request-Id')
            ?? bin2hex(random_bytes(8));

        $response = new JsonResponse(
            $problem,
            $problem['status'],
            ['Content-Type' => 'application/problem+json'],
        );

        if ($e instanceof HttpExceptionInterface) {
            $response->headers->add($e->getHeaders());
        }

        $event->setResponse($response);
    }

    private function validationFailure(\Throwable $e): ?ValidationFailedException
    {
        return match (true) {
            $e instanceof ValidationFailedException                => $e,
            $e->getPrevious() instanceof ValidationFailedException => $e->getPrevious(),
            default                                                => null,
        };
    }

    private function fromValidation(ValidationFailedException $e): array
    {
        $errors = [];

        foreach ($e->getViolations() as $violation) {
            $cause = $violation->getCause();

            $errorCode = $cause instanceof DomainException
                ? $cause->errorCode()
                : (self::NATIVE_CODES[$violation->getCode()] ?? InvalidValue::INVALID);
            $params  = $cause instanceof DomainException ? $cause->params() : [];
            $mapping = $this->mapper->find($errorCode);
            $path    = $violation->getPropertyPath();

            $errors[] = [
                'field'   => $path !== '' ? $path : $mapping?->field,
                'code'    => $errorCode,
                'message' => $mapping !== null
                    ? $this->mapper->render($mapping, $params)
                    : $violation->getMessage(),
                'params'  => $params,
            ];
        }

        return [
            'type'   => 'VALIDATION_ERROR',
            'title'  => 'Los datos enviados no son válidos',
            'status' => 422,
            'errors' => $errors,
        ];
    }

    private function fromDomain(DomainException $e): array
    {
        $mapping = $this->mapper->find($e->errorCode());

        if ($mapping === null) {
            $this->appLogger->warning('Unmapped domain error code', [
                'errorCode' => $e->errorCode(),
                'exception' => $e,
            ]);

            return $this->internal($e);
        }

        $message = $this->mapper->render($mapping, $e->params());

        $problem = [
            'type'   => $e->errorCode(),
            'title'  => $message,
            'status' => $mapping->status,
        ];

        if ($e->params() !== []) {
            $problem['params'] = $e->params();
        }

        // Si el mapper indica campo, se añade también en errors[] para que el
        // cliente lo trate igual que un error de validación.
        if ($mapping->field !== null) {
            $problem['errors'] = [[
                'field'   => $mapping->field,
                'code'    => $e->errorCode(),
                'message' => $message,
                'params'  => $e->params(),
            ]];
        }

        return $problem;
    }

    private function fromHttp(HttpExceptionInterface $e): array
    {
        $status = $e->getStatusCode();
        $text   = Response::$statusTexts[$status] ?? 'Error';

        return [
            'type'   => strtoupper(str_replace([' ', '-'], '_', $text)),
            'title'  => $status < 500 && $e->getMessage() !== '' ? $e->getMessage() : $text,
            'status' => $status,
        ];
    }

    private function internal(\Throwable $e): array
    {
        $problem = ['type' => 'INTERNAL_ERROR', 'title' => 'Error interno', 'status' => 500];

        if ($this->debug) {
            $problem['debug'] = [
                'exception' => $e::class,
                'message'   => $e->getMessage(),
                'at'        => $e->getFile() . ':' . $e->getLine(),
            ];
        }

        return $problem;
    }
}
```

---

## 7. Formato de las respuestas

**Error de validación** (varios campos a la vez):

```json
{
  "type": "VALIDATION_ERROR",
  "title": "Los datos enviados no son válidos",
  "status": 422,
  "errors": [
    {
      "field": "quantity",
      "code": "VALUE_OUT_OF_RANGE",
      "message": "Debe estar entre 1 y 999",
      "params": { "min": 1, "max": 999 }
    }
  ],
  "traceId": "a3f9c2e17b4d8e05"
}
```

**Error de dominio sin campo**:

```json
{
  "type": "ORDER_ALREADY_PAID",
  "title": "El pedido 7f3c… ya está pagado",
  "status": 409,
  "params": { "orderId": "7f3c…" },
  "traceId": "a3f9c2e17b4d8e05"
}
```

**Error de dominio con campo en el mapper**: igual que el anterior más `errors[]` con un elemento.

**Error inesperado**:

```json
{ "type": "INTERNAL_ERROR", "title": "Error interno", "status": 500, "traceId": "a3f9c2e17b4d8e05" }
```

El cliente debe decidir siempre a partir de `type` / `code`, nunca del texto.

---

## 8. Logging

El listener no registra las excepciones porque Symfony ya lo hace y saldrían duplicadas. Solo hay que bajar el nivel de las de dominio para que no aparezcan como críticas ni disparen alertas:

```yaml
# config/packages/framework.yaml
framework:
  exceptions:
    App\Shared\Domain\Exception\DomainException:
      log_level: info
```

Si se usa Sentry, configurar que ignore `DomainException` o que solo capture errores de nivel `error` o superior. Si se usa OpenTelemetry, sustituir el `traceId` del listener por el trace id del span actual.

---

## 9. Tests obligatorios

1. **Todo `errorCode` está mapeado.** Test que recorra con reflexión todas las clases de `src/` que extienden `DomainException`, recoja sus constantes string (`ERROR_CODE` y las de `InvalidValue`) y compruebe que cada una existe en `ErrorCodeMapper::mappedCodes()`.
2. **No hay códigos duplicados.** El constructor del mapper ya lanza `LogicException`; añadir un test que arranque el kernel y obtenga el servicio para detectarlo.
3. **Formato de los códigos.** Todos cumplen `/^[A-Z][A-Z0-9]*(_[A-Z0-9]+)+$/`.
4. **Listener**, un test funcional por caso:
   - DTO inválido con varios errores → 422 con todos en `errors[]` y los `field` correctos.
   - VO inválido vía `ValidVO` → `code` del dominio, mensaje del mapper con placeholders resueltos.
   - Excepción de dominio sin campo → status y mensaje del mapper, sin `errors[]`.
   - Excepción de dominio con campo en el mapper → incluye `errors[]`.
   - Código sin mapear → 500 y warning en el log.
   - Excepción no controlada → 500 sin detalles con `debug` desactivado.
   - JSON malformado → 400.
   - En todos: `Content-Type: application/problem+json` y `traceId` presente.

---

## 10. Plan de migración (seguir en este orden)

1. **Explorar**: listar las excepciones existentes, los listeners o subscribers de `kernel.exception`, la configuración `framework.exceptions`, cómo se validan las requests y el formato de error actual. Presentar el inventario antes de modificar nada.
2. **Crear la base de dominio**: `DomainException` e `InvalidValue` en Shared.
3. **Crear el mapper**: `ErrorMapping`, `ErrorMappingProvider`, `ErrorCodeMapper` y `SharedErrorMappings`.
4. **Crear `ValidVO` y `ValidVOValidator`.**
5. **Crear `ApiExceptionListener`** y eliminar o desactivar los listeners de errores anteriores para que no compitan.
6. **Migrar contexto a contexto**: hacer que cada excepción extienda `DomainException`, añadir su `ERROR_CODE`, crear el `{Context}ErrorMappings` con status, mensaje y campo. Si una excepción existente recibía un status HTTP o un nombre de campo, moverlo al mapper.
7. **Sustituir validaciones duplicadas**: en los DTOs, reemplazar las constraints que repiten reglas de un VO por `#[ValidVO(...)]`. Mantener las estructurales (`NotBlank`, `NotNull`, `Type`).
8. **Configurar logging** (sección 8).
9. **Añadir los tests** de la sección 9 y ejecutar la suite completa y el análisis estático (PHPStan / Psalm si existen).

## 11. Criterios de aceptación

- [ ] Ninguna clase de `Domain/` referencia status HTTP, nombres de campos del payload ni componentes HTTP de Symfony.
- [ ] Todas las excepciones de negocio extienden `DomainException` y tienen `errorCode` string.
- [ ] Todas mantienen `message`, `code` y `previous`; las excepciones envueltas se pasan como `previous`.
- [ ] Todos los `errorCode` están en el mapper con status y mensaje; el campo solo donde aplica.
- [ ] `ApiExceptionListener` es el único sitio que construye respuestas de error.
- [ ] Todas las respuestas de error siguen el formato de la sección 7.
- [ ] Los errores 500 no exponen información interna fuera de debug.
- [ ] Los tests de la sección 9 pasan.

## 12. Qué NO hacer

- No usar el `code` entero nativo de la excepción como código de negocio ni para el mapeo.
- No lanzar `HttpException` ni subclases desde el dominio ni desde los casos de uso.
- No construir respuestas de error en controllers con `try/catch`; dejar que la excepción llegue al listener.
- No devolver al cliente el mensaje interno de la excepción de dominio.
- No reutilizar un `errorCode` existente para otro significado.
