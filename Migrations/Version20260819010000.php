<?php

namespace KimaiPlugin\KanbanBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Moves the accent color from individual tasks to the list/column: a whole
 * column (e.g. "Urgente") gets a colored header, instead of each task
 * carrying its own color.
 */
final class Version20260819010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Moves the accent color from kanban tasks to kanban lists';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE kimai_kanban_lists ADD color VARCHAR(7) DEFAULT NULL');
        $this->addSql('ALTER TABLE kimai_kanban_tasks DROP COLUMN color');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE kimai_kanban_tasks ADD color VARCHAR(7) DEFAULT NULL');
        $this->addSql('ALTER TABLE kimai_kanban_lists DROP COLUMN color');
    }
}
