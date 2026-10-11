<?php

declare(strict_types=1);

namespace App\UI\Http\Controller\Api\User;

use App\Application\Command\DeleteUser\DeleteUserCommand;
use App\UI\Http\Controller\Api\ApiController;
use App\UI\Http\OpenApi\Attribute\InternalErrorResponse;
use App\UI\Http\OpenApi\Attribute\ProblemResponse;
use App\UI\Http\Response\ApiResponder;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[OA\Tag(name: 'Users')]
final class DeleteUserController extends ApiController
{
    #[OA\Delete(
        summary: 'Delete a user',
        description: 'Only allowed when none of the user\'s tasks is pending or in progress. Their completed tasks are deleted with them.',
    )]
    #[OA\Parameter(name: 'id', description: 'User identifier', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Response(response: Response::HTTP_NO_CONTENT, description: 'User deleted, together with their completed tasks')]
    #[ProblemResponse(Response::HTTP_NOT_FOUND, 'User not found (also for an id that is not a UUID)', 'USER_NOT_FOUND', 'User b7e4d2c1-5a3f-4e8b-8c9d-6f1a2b3c4d5e does not exist.', ['user_id' => 'b7e4d2c1-5a3f-4e8b-8c9d-6f1a2b3c4d5e'])]
    #[ProblemResponse(
        Response::HTTP_CONFLICT,
        'The user still has tasks that are not completed',
        'USER_DELETION_NOT_ALLOWED',
        'User b7e4d2c1-5a3f-4e8b-8c9d-6f1a2b3c4d5e still has 2 unfinished task(s); complete them before deleting the user.',
        ['user_id' => 'b7e4d2c1-5a3f-4e8b-8c9d-6f1a2b3c4d5e', 'unfinished_tasks' => 2],
    )]
    #[InternalErrorResponse]
    #[Route('/api/users/{id}', name: 'api_user_delete', requirements: ['id' => Requirement::UUID], methods: ['DELETE'])]
    public function __invoke(string $id, ApiResponder $responder): Response
    {
        $this->dispatch(new DeleteUserCommand($id));

        return $responder->noContent();
    }
}
