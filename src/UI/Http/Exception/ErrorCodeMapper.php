<?php

declare(strict_types=1);

namespace App\UI\Http\Exception;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Maps error codes to their HTTP representation (status, public message, field).
 * The mapping lives in config/packages/error_mapping.yaml.
 */
final readonly class ErrorCodeMapper
{
    /** @var array<string, ErrorMapping> */
    private array $mappings;

    /**
     * @param array<array-key, mixed> $codes errorCode => {status, message, field?}
     */
    public function __construct(
        #[Autowire(param: 'app.error_mapping.codes')]
        array $codes,
    ) {
        $mappings = [];
        foreach ($codes as $errorCode => $definition) {
            $mappings[(string) $errorCode] = ErrorMapping::fromConfig((string) $errorCode, $definition);
        }

        $this->mappings = $mappings;
    }

    public function find(string $errorCode): ?ErrorMapping
    {
        return $this->mappings[$errorCode] ?? null;
    }

    /**
     * @return list<string>
     */
    public function mappedCodes(): array
    {
        return \array_keys($this->mappings);
    }

    /**
     * Replaces the {placeholders} of the public message with the exception params.
     *
     * @param array<string, scalar|null> $params
     */
    public function render(ErrorMapping $mapping, array $params): string
    {
        $replacements = [];
        foreach ($params as $key => $value) {
            $replacements['{' . $key . '}'] = match (true) {
                null === $value  => '',
                \is_bool($value) => $value ? 'true' : 'false',
                default          => (string) $value,
            };
        }

        return \strtr($mapping->message, $replacements);
    }
}
