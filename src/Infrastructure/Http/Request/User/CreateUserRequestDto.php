<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Request\User;

use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\User\UserName;
use App\Infrastructure\Http\Request\ValidationMessages;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final class CreateUserRequestDto
{
    #[Assert\NotBlank(message: ValidationMessages::REQUIRED)]
    #[Assert\Type(type: 'string', message: ValidationMessages::TYPE)]
    #[OA\Property(type: 'string', format: 'email', maxLength: Email::MAX_LENGTH, example: 'jane.doe@example.com')]
    public $email;

    #[Assert\NotBlank(message: ValidationMessages::REQUIRED)]
    #[Assert\Type(type: 'string', message: ValidationMessages::TYPE)]
    #[OA\Property(type: 'string', maxLength: UserName::MAX_LENGTH, minLength: UserName::MIN_LENGTH, example: 'Jane Doe')]
    public $name;
}
