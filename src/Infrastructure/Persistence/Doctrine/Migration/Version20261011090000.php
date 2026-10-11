<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261011090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create tickets.users table';
    }

    public function up(Schema $schema): void
    {
        // Usually already there: `make db-migrate` creates it first, because the migrations table lives in it too.
        $this->addSql('CREATE SCHEMA IF NOT EXISTS tickets');
        $this->addSql('CREATE TABLE tickets.users (id UUID NOT NULL, email VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2C9AAE32E7927C74 ON tickets.users (email)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE tickets.users');
    }
}
