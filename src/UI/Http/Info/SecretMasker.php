<?php

declare(strict_types=1);

namespace App\UI\Http\Info;

/**
 * Hides credentials before anything reaches the /info page: values whose key looks secret,
 * and the password part of URLs/DSNs (`scheme://user:password@host`).
 */
final class SecretMasker
{
    public const MASK = '••••••••';

    private const SECRET_KEY = '/(^|[_.\-])(pass(word)?|pwd|secret|token|api_?key|private_?key|key|credentials?|auth(orization)?|cookie|salt|signature)([_.\-]|$)/i';

    // The password may itself contain "@" or ":", so it runs up to the last "@" before the host.
    private const URL_CREDENTIALS = '~^([a-z][a-z0-9+.\-]*://[^/:@]*:)(.+)(@[^@/?#]+(?:[/?#].*)?)$~is';

    public function isSecretKey(string $key): bool
    {
        return 1 === \preg_match(self::SECRET_KEY, $key);
    }

    public function mask(string $key, mixed $value): mixed
    {
        if (\is_array($value)) {
            return $this->maskArray($value);
        }

        if (null === $value || '' === $value || \is_bool($value)) {
            return $value;
        }

        if ($this->isSecretKey($key)) {
            return self::MASK;
        }

        return \is_string($value) ? $this->maskUrl($value) : $value;
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @return array<array-key, mixed>
     */
    public function maskArray(array $values): array
    {
        foreach ($values as $key => $value) {
            $values[$key] = $this->mask((string) $key, $value);
        }

        return $values;
    }

    public function maskUrl(string $value): string
    {
        return \preg_replace(self::URL_CREDENTIALS, '$1' . self::MASK . '$3', $value) ?? $value;
    }
}
