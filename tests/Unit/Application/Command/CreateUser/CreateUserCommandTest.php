<?php

declare(strict_types=1);

namespace App\Tests\Application\Command\CreateUser;

use App\Application\Command\CreateUser\CreateUserCommand;
use PHPUnit\Framework\TestCase;

final class CreateUserCommandTest extends TestCase
{
    public function test_getters_return_given_values(): void
    {
        $req = new CreateUserCommand('user@example.com', 'User Name');

        $this->assertSame('user@example.com', $req->getEmail());
        $this->assertSame('User Name', $req->getName());
    }

    public function test_allows_empty_strings_currently(): void
    {
        $req = new CreateUserCommand('', '');

        $this->assertSame('', $req->getEmail());
        $this->assertSame('', $req->getName());
    }

    public function test_does_not_trim_or_normalize_input(): void
    {
        $req = new CreateUserCommand('  user@example.com  ', " Name\t");

        $this->assertSame('  user@example.com  ', $req->getEmail());
        $this->assertSame(" Name\t", $req->getName());
    }
}
