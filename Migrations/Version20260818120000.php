<?php

namespace KimaiPlugin\KanbanBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260818120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Creates the tables for the Kanban & Task Lists plugin';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE kimai_kanban_boards (
                id INT AUTO_INCREMENT NOT NULL,
                project_id INT NOT NULL,
                activity_id INT DEFAULT NULL,
                title VARCHAR(150) NOT NULL,
                created_at DATETIME NOT NULL,
                UNIQUE INDEX UNIQ_KANBAN_BOARD_PROJECT (project_id),
                INDEX IDX_KANBAN_BOARD_ACTIVITY (activity_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
        ');

        $this->addSql('
            CREATE TABLE kimai_kanban_lists (
                id INT AUTO_INCREMENT NOT NULL,
                board_id INT NOT NULL,
                title VARCHAR(150) NOT NULL,
                position INT NOT NULL,
                INDEX IDX_KANBAN_LIST_BOARD (board_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
        ');

        $this->addSql('
            CREATE TABLE kimai_kanban_tasks (
                id INT AUTO_INCREMENT NOT NULL,
                list_id INT NOT NULL,
                created_by_id INT DEFAULT NULL,
                active_timesheet_id INT DEFAULT NULL,
                title VARCHAR(255) NOT NULL,
                description LONGTEXT DEFAULT NULL,
                position INT NOT NULL,
                start_date DATE DEFAULT NULL,
                due_date DATE DEFAULT NULL,
                end_date DATE DEFAULT NULL,
                is_done TINYINT(1) NOT NULL,
                created_at DATETIME NOT NULL,
                INDEX IDX_KANBAN_TASK_LIST (list_id),
                INDEX IDX_KANBAN_TASK_CREATED_BY (created_by_id),
                UNIQUE INDEX UNIQ_KANBAN_TASK_ACTIVE_TIMESHEET (active_timesheet_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
        ');

        $this->addSql('
            CREATE TABLE kimai_kanban_task_timesheets (
                task_id INT NOT NULL,
                timesheet_id INT NOT NULL,
                INDEX IDX_KANBAN_TT_TASK (task_id),
                INDEX IDX_KANBAN_TT_TIMESHEET (timesheet_id),
                PRIMARY KEY(task_id, timesheet_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
        ');

        $this->addSql('
            CREATE TABLE kimai_kanban_checklist_items (
                id INT AUTO_INCREMENT NOT NULL,
                task_id INT NOT NULL,
                text VARCHAR(500) NOT NULL,
                is_checked TINYINT(1) NOT NULL,
                position INT NOT NULL,
                INDEX IDX_KANBAN_CHECKLIST_TASK (task_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
        ');

        $this->addSql('ALTER TABLE kimai_kanban_boards ADD CONSTRAINT FK_KANBAN_BOARD_PROJECT FOREIGN KEY (project_id) REFERENCES kimai2_projects (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE kimai_kanban_boards ADD CONSTRAINT FK_KANBAN_BOARD_ACTIVITY FOREIGN KEY (activity_id) REFERENCES kimai2_activities (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE kimai_kanban_lists ADD CONSTRAINT FK_KANBAN_LIST_BOARD FOREIGN KEY (board_id) REFERENCES kimai_kanban_boards (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE kimai_kanban_tasks ADD CONSTRAINT FK_KANBAN_TASK_LIST FOREIGN KEY (list_id) REFERENCES kimai_kanban_lists (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE kimai_kanban_tasks ADD CONSTRAINT FK_KANBAN_TASK_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES kimai2_users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE kimai_kanban_tasks ADD CONSTRAINT FK_KANBAN_TASK_ACTIVE_TS FOREIGN KEY (active_timesheet_id) REFERENCES kimai2_timesheet (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE kimai_kanban_task_timesheets ADD CONSTRAINT FK_KANBAN_TT_TASK FOREIGN KEY (task_id) REFERENCES kimai_kanban_tasks (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE kimai_kanban_task_timesheets ADD CONSTRAINT FK_KANBAN_TT_TIMESHEET FOREIGN KEY (timesheet_id) REFERENCES kimai2_timesheet (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE kimai_kanban_checklist_items ADD CONSTRAINT FK_KANBAN_CHECKLIST_TASK FOREIGN KEY (task_id) REFERENCES kimai_kanban_tasks (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE kimai_kanban_checklist_items');
        $this->addSql('DROP TABLE kimai_kanban_task_timesheets');
        $this->addSql('DROP TABLE kimai_kanban_tasks');
        $this->addSql('DROP TABLE kimai_kanban_lists');
        $this->addSql('DROP TABLE kimai_kanban_boards');
    }
}
