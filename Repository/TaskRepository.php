<?php

namespace KimaiPlugin\KanbanBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use KimaiPlugin\KanbanBundle\Entity\Board;
use KimaiPlugin\KanbanBundle\Entity\Task;
use KimaiPlugin\KanbanBundle\Entity\TaskList;

/**
 * @extends ServiceEntityRepository<Task>
 */
class TaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Task::class);
    }

    public function getNextPosition(TaskList $list): int
    {
        $max = $this->createQueryBuilder('t')
            ->select('MAX(t.position)')
            ->where('t.taskList = :list')
            ->setParameter('list', $list)
            ->getQuery()
            ->getSingleScalarResult();

        return $max === null ? 0 : ((int) $max + 1);
    }

    /**
     * All tasks belonging to a board, flat, for the "lista de tarefas" view.
     *
     * @return Task[]
     */
    public function findByBoard(Board $board): array
    {
        return $this->createQueryBuilder('t')
            ->join('t.taskList', 'l')
            ->addSelect('l')
            ->where('l.board = :board')
            ->setParameter('board', $board)
            ->orderBy('l.position', 'ASC')
            ->addOrderBy('t.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * The task currently being tracked by the given user, if any (a user can
     * only have one running Kimai timesheet at a time).
     */
    public function findRunningTaskForUser(int $userId): ?Task
    {
        return $this->createQueryBuilder('t')
            ->join('t.activeTimesheet', 'ts')
            ->where('ts.user = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
