<?php

namespace KimaiPlugin\KanbanBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Lets a checklist item carry its own one-level-deep sub-checklist
 * (a self-referencing parent_id on kimai_kanban_checklist_items).
 */
final class Version20260819020000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds a nullable parent_id to kimai_kanban_checklist_items for nested sub-checklists';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE kimai_kanban_checklist_items ADD parent_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE kimai_kanban_checklist_items ADD CONSTRAINT FK_KANBAN_CHECKLIST_PARENT FOREIGN KEY (parent_id) REFERENCES kimai_kanban_checklist_items (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_KANBAN_CHECKLIST_PARENT ON kimai_kanban_checklist_items (parent_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE kimai_kanban_checklist_items DROP FOREIGN KEY FK_KANBAN_CHECKLIST_PARENT');
        $this->addSql('DROP INDEX IDX_KANBAN_CHECKLIST_PARENT ON kimai_kanban_checklist_items');
        $this->addSql('ALTER TABLE kimai_kanban_checklist_items DROP COLUMN parent_id');
    }
}
