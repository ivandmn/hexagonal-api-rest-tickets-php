<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261011090100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create task table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE task (id UUID NOT NULL, user_id UUID DEFAULT NULL, title VARCHAR(255) NOT NULL, description VARCHAR(1000) NOT NULL, status VARCHAR(30) NOT NULL, priority VARCHAR(30) NOT NULL, due_date TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_527EDB25A76ED395 ON task (user_id)');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB25A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE task');
    }
}
