<?php

namespace KimaiPlugin\KanbanBundle;

use App\Plugin\PluginInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Kanban & Task Lists plugin for Kimai.
 *
 * Adds a Trello-like Kanban board and a task-list view per project, with
 * checklists ("sublistas"), due/start/end dates and start/pause buttons
 * that drive Kimai's native Timesheet tracking.
 */
class KanbanBundle extends Bundle implements PluginInterface
{
}
