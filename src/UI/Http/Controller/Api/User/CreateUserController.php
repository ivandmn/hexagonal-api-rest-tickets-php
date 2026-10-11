<?php

declare(strict_types=1);

namespace App\UI\Http\Controller\Api\User;

use App\Application\Command\CommandBusInterface;
use App\Application\Command\CreateUser\CreateUserCommand;
use App\Application\Command\CreateUser\CreateUserResponse;
use App\Application\Query\QueryBusInterface;
use App\Domain\Exception\User\InvalidUserEmailException;
use App\Domain\Exception\User\InvalidUserNameException;
use App\Domain\Exception\User\UserAlreadyExistsException;
use App\Infrastructure\Exception\InvalidRequestException;
use App\Infrastructure\Exception\InvalidRequestParameterException;
use App\Infrastructure\Http\ApiResponse;
use App\Infrastructure\Http\Request\User\CreateUserRequestDto;
use App\Infrastructure\Service\ApiRequestValidator;
use App\Infrastructure\Service\MonologLogger;
use App\Infrastructure\Symfony\Controller\BaseController;
use App\UI\Http\OpenApi\Attribute\ErrorResponse;
use App\UI\Http\OpenApi\Attribute\InternalErrorResponse;
use App\UI\Http\OpenApi\Attribute\SuccessResponse;
use App\UI\Http\OpenApi\Schema\User;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[OA\Tag(name: 'Users')]
class CreateUserController extends BaseController
{
    public function __construct(
        QueryBusInterface $queryBus,
        CommandBusInterface $commandBus,
        private readonly MonologLogger $logger,
        private readonly ApiRequestValidator $apiRequestValidator,
    ) {
        parent::__construct($queryBus, $commandBus);
    }

    #[OA\Post(summary: 'Create a user')]
    #[OA\RequestBody(required: true, content: new Model(type: CreateUserRequestDto::class))]
    #[SuccessResponse(User::class, 'User created')]
    #[ErrorResponse(Response::HTTP_BAD_REQUEST, 'Invalid payload', 'Invalid user email')]
    #[ErrorResponse(Response::HTTP_CONFLICT, 'Email already registered', 'User with this email already exists')]
    #[InternalErrorResponse]
    #[Route('/api/users', name: 'api_user_create', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        try {
            /** @var CreateUserRequestDto $data */
            $data = $this->apiRequestValidator->validate($request, CreateUserRequestDto::class);

            $command = new CreateUserCommand(
                $data->email,
                $data->name,
            );

            /** @var CreateUserResponse $response */
            $response = $this->commandHandler($command);

            return ApiResponse::success($response);
        } catch (InvalidRequestException|InvalidRequestParameterException|InvalidUserEmailException|InvalidUserNameException $exception) {
            return ApiResponse::error($exception->getMessage(), Response::HTTP_BAD_REQUEST);
        } catch (UserAlreadyExistsException $exception) {
            return ApiResponse::error($exception->getMessage(), Response::HTTP_CONFLICT);
        } catch (\Throwable $exception) {
            $this->logger->error(
                \sprintf('Error Creating User: %s', $exception->getMessage()),
                ['exception' => $exception],
            );

            return ApiResponse::internalError();
        }
    }
}
