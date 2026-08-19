<?php

namespace KimaiPlugin\KanbanBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use KimaiPlugin\KanbanBundle\Entity\Board;
use KimaiPlugin\KanbanBundle\Entity\TaskList;

/**
 * @extends ServiceEntityRepository<TaskList>
 */
class TaskListRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TaskList::class);
    }

    public function getNextPosition(Board $board): int
    {
        $max = $this->createQueryBuilder('l')
            ->select('MAX(l.position)')
            ->where('l.board = :board')
            ->setParameter('board', $board)
            ->getQuery()
            ->getSingleScalarResult();

        return $max === null ? 0 : ((int) $max + 1);
    }
}
