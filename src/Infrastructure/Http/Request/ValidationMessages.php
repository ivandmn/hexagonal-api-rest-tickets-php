<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Request;

final class ValidationMessages
{
    public const REQUIRED        = 'Missing required parameter "{{ label }}"';
    public const TYPE            = 'Parameter "{{ label }}" must be of type {{ type }}';
    public const POSITIVE_NUMBER = 'Parameter "{{ label }}" must be positive number';
    public const RANGE           = 'Parameter "{{ label }}" must be between {{ min }} and {{ max }}';
    public const LENGTH_MIN      = 'Parameter "{{ label }}" must have at least {{ limit }} characters';
    public const LENGTH_MAX      = 'Parameter "{{ label }}" cannot exceed {{ limit }} characters';
    public const CHOICE          = 'Parameter "{{ label }}" must be one of the allowed values';
    public const DATE_ATOM       = 'Parameter "{{ label }}" must be a valid ISO8601 (ATOM) datetime';
}
