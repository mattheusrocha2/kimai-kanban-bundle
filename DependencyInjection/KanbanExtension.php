<?php

namespace KimaiPlugin\KanbanBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class KanbanExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');
    }

    /**
     * Registers this plugin's own permissions so they show up in
     * Administration > Roles and can be assigned per role.
     */
    public function prepend(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('kimai', [
            'permissions' => [
                'sets' => [
                    'KANBAN' => [
                        'view_kanban',
                        'create_kanban',
                        'edit_kanban',
                        'delete_kanban',
                    ],
                ],
                'maps' => [
                    'ROLE_USER' => ['KANBAN'],
                    'ROLE_TEAMLEAD' => ['KANBAN'],
                    'ROLE_ADMIN' => ['KANBAN'],
                ],
                'roles' => [
                    'ROLE_SUPER_ADMIN' => [
                        'view_kanban',
                        'create_kanban',
                        'edit_kanban',
                        'delete_kanban',
                    ],
                ],
            ],
        ]);
    }
}
