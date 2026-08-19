<?php

namespace KimaiPlugin\KanbanBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use KimaiPlugin\KanbanBundle\Entity\TaskAttachment;

/**
 * @extends ServiceEntityRepository<TaskAttachment>
 */
class TaskAttachmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TaskAttachment::class);
    }
}
