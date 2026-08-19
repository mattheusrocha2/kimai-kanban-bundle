<?php

namespace KimaiPlugin\KanbanBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Creates the table for task attachments (pasted screenshots or file-picker
 * uploads, image-only). Files themselves are stored on disk under
 * var/kanban/attachments, see TaskAttachmentStorage.
 */
final class Version20260819030000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Creates the kimai_kanban_task_attachments table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE kimai_kanban_task_attachments (
                id INT AUTO_INCREMENT NOT NULL,
                task_id INT NOT NULL,
                uploaded_by_id INT DEFAULT NULL,
                filename VARCHAR(255) NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                mime_type VARCHAR(100) NOT NULL,
                size INT NOT NULL,
                created_at DATETIME NOT NULL,
                INDEX IDX_KANBAN_ATTACHMENT_TASK (task_id),
                INDEX IDX_KANBAN_ATTACHMENT_UPLOADED_BY (uploaded_by_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
        ');

        $this->addSql('ALTER TABLE kimai_kanban_task_attachments ADD CONSTRAINT FK_KANBAN_ATTACHMENT_TASK FOREIGN KEY (task_id) REFERENCES kimai_kanban_tasks (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE kimai_kanban_task_attachments ADD CONSTRAINT FK_KANBAN_ATTACHMENT_UPLOADED_BY FOREIGN KEY (uploaded_by_id) REFERENCES kimai2_users (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE kimai_kanban_task_attachments');
    }
}
