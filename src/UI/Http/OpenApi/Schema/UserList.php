<?php

declare(strict_types=1);

namespace App\UI\Http\OpenApi\Schema;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

#[OA\Schema(required: ['data', 'meta'])]
final class UserList
{
    /** @var list<User> */
    #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: User::class)))]
    public array $data;

    #[OA\Property(ref: new Model(type: UserListMeta::class))]
    public UserListMeta $meta;
}
