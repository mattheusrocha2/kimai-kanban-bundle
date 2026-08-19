<?php

namespace KimaiPlugin\KanbanBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260819000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds the optional accent color column to kanban tasks';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE kimai_kanban_tasks ADD color VARCHAR(7) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE kimai_kanban_tasks DROP COLUMN color');
    }
}
