<?php

namespace KimaiPlugin\KanbanBundle\Command;

use App\Command\AbstractBundleInstallerCommand;

/**
 * Registers `bin/console kimai:bundle:kanban:install`, which runs this
 * plugin's database migrations and publishes its CSS/JS assets.
 */
class KanbanInstallCommand extends AbstractBundleInstallerCommand
{
    protected function getBundleCommandNamePart(): string
    {
        return 'kanban';
    }

    protected function hasAssets(): bool
    {
        return true;
    }

    protected function getMigrationConfigFilename(): ?string
    {
        return $this->getRootDirectory() . '/var/plugins/KanbanBundle/Migrations/migrations.yaml';
    }
}
