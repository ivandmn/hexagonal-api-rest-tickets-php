<?php

declare(strict_types=1);

namespace App\UI\Http\Request\User;

use App\Application\Command\CreateUser\CreateUserCommand;
use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\User\UserEmail;
use App\Domain\ValueObject\User\UserName;
use App\UI\Http\Validation\ValidVO;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(required: ['email', 'name'])]
final readonly class CreateUserRequest
{
    public function __construct(
        #[Assert\Sequentially([new Assert\NotBlank(), new ValidVO(UserEmail::class)])]
        #[OA\Property(format: 'email', maxLength: Email::MAX_LENGTH, example: 'jane.doe@example.com')]
        public ?string $email = null,
        #[Assert\Sequentially([new Assert\NotBlank(), new ValidVO(UserName::class)])]
        #[OA\Property(maxLength: UserName::MAX_LENGTH, minLength: UserName::MIN_LENGTH, example: 'Jane Doe')]
        public ?string $name = null,
    ) {
    }

    public function toCommand(): CreateUserCommand
    {
        return new CreateUserCommand((string) $this->email, (string) $this->name);
    }
}
