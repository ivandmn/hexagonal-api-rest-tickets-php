<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261011101603 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Deleting a user deletes their tasks (task.user_id ON DELETE CASCADE)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tickets.task DROP CONSTRAINT fk_93b7c487a76ed395');
        $this->addSql('ALTER TABLE tickets.task ADD CONSTRAINT FK_93B7C487A76ED395 FOREIGN KEY (user_id) REFERENCES tickets.users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tickets.task DROP CONSTRAINT FK_93B7C487A76ED395');
        $this->addSql('ALTER TABLE tickets.task ADD CONSTRAINT fk_93b7c487a76ed395 FOREIGN KEY (user_id) REFERENCES tickets.users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
