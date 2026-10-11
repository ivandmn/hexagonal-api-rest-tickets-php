<?php

declare(strict_types=1);

namespace App\UI\Http\OpenApi\Schema;

use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\User\UserName;
use OpenApi\Attributes as OA;

#[OA\Schema(required: ['id', 'email', 'name', 'created_at', 'updated_at'])]
final class User
{
    #[OA\Property(format: 'uuid', example: 'b7e4d2c1-5a3f-4e8b-8c9d-6f1a2b3c4d5e')]
    public string $id;

    #[OA\Property(format: 'email', maxLength: Email::MAX_LENGTH, example: 'jane.doe@example.com')]
    public string $email;

    #[OA\Property(maxLength: UserName::MAX_LENGTH, minLength: UserName::MIN_LENGTH, example: 'Jane Doe')]
    public string $name;

    #[OA\Property(format: 'date-time', example: '2026-10-11T08:00:00+00:00')]
    public string $createdAt;

    #[OA\Property(format: 'date-time', nullable: true)]
    public ?string $updatedAt;
}
