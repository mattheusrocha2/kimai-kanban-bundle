<?php

namespace KimaiPlugin\KanbanBundle\Repository;

use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use KimaiPlugin\KanbanBundle\Entity\Board;

/**
 * @extends ServiceEntityRepository<Board>
 */
class BoardRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Board::class);
    }

    public function findByProject(Project $project): ?Board
    {
        return $this->findOneBy(['project' => $project]);
    }
}
