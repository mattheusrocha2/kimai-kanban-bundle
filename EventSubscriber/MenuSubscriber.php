<?php

namespace KimaiPlugin\KanbanBundle\EventSubscriber;

use App\Event\ConfigureMainMenuEvent;
use App\Utils\MenuItemModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Adds a "Kanban" entry to Kimai's main navigation, pointing at the plugin's
 * project picker (from there the user reaches each project's board).
 */
class MenuSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly AuthorizationCheckerInterface $authorizationChecker)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConfigureMainMenuEvent::class => ['onMainMenuConfigure', 100],
        ];
    }

    public function onMainMenuConfigure(ConfigureMainMenuEvent $event): void
    {
        if (!$this->authorizationChecker->isGranted('view_kanban')) {
            return;
        }

        $event->getMenu()->addChild(
            new MenuItemModel('kanban', 'menu.kanban', 'kanban_projects', [], 'fas fa-columns')
        );
    }
}
