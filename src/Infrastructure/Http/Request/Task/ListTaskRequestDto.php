<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Request\Task;

use App\Application\Query\ListTasks\ListTasksQuery;
use App\Infrastructure\Http\Request\ValidationMessages;
use Symfony\Component\Validator\Constraints as Assert;

final class ListTaskRequestDto
{
    #[Assert\Type(type: 'string', message: ValidationMessages::TYPE)]
    public $status;

    #[Assert\Type(type: 'string', message: ValidationMessages::TYPE)]
    public $priority;

    #[Assert\Type(type: 'numeric', message: ValidationMessages::TYPE)]
    #[Assert\Positive(message: ValidationMessages::POSITIVE_NUMBER)]
    public $page;

    #[Assert\Type(type: 'numeric', message: ValidationMessages::TYPE)]
    #[Assert\Range(notInRangeMessage: ValidationMessages::RANGE, min: 1, max: ListTasksQuery::MAX_LIMIT)]
    public $limit;
}
