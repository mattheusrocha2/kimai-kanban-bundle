<?php

namespace KimaiPlugin\KanbanBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use KimaiPlugin\KanbanBundle\Entity\ChecklistItem;
use KimaiPlugin\KanbanBundle\Entity\Task;

/**
 * @extends ServiceEntityRepository<ChecklistItem>
 */
class ChecklistItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChecklistItem::class);
    }

    public function getNextPosition(Task $task): int
    {
        $max = $this->createQueryBuilder('c')
            ->select('MAX(c.position)')
            ->where('c.task = :task')
            ->setParameter('task', $task)
            ->getQuery()
            ->getSingleScalarResult();

        return $max === null ? 0 : ((int) $max + 1);
    }
}
