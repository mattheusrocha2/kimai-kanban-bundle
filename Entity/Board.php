<?php

namespace KimaiPlugin\KanbanBundle\Entity;

use App\Entity\Activity;
use App\Entity\Project;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use KimaiPlugin\KanbanBundle\Repository\BoardRepository;

/**
 * One Kanban board (with its lists/columns) belonging to exactly one Kimai Project.
 */
#[ORM\Entity(repositoryClass: BoardRepository::class)]
#[ORM\Table(name: 'kimai_kanban_boards')]
#[ORM\UniqueConstraint(name: 'UNIQ_KANBAN_BOARD_PROJECT', columns: ['project_id'])]
class Board
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(name: 'project_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Project $project = null;

    /**
     * The Activity used for every Timesheet entry created from "iniciar" on
     * a task of this board, so tracked time shows up in Kimai's normal
     * reports/exports/invoicing for the project.
     */
    #[ORM\ManyToOne(targetEntity: Activity::class)]
    #[ORM\JoinColumn(name: 'activity_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Activity $activity = null;

    #[ORM\Column(name: 'title', type: 'string', length: 150)]
    private string $title = 'Kanban';

    #[ORM\OneToMany(mappedBy: 'board', targetEntity: TaskList::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $lists;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->lists = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(Project $project): self
    {
        $this->project = $project;

        return $this;
    }

    public function getActivity(): ?Activity
    {
        return $this->activity;
    }

    public function setActivity(?Activity $activity): self
    {
        $this->activity = $activity;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    /**
     * @return Collection<int, TaskList>
     */
    public function getLists(): Collection
    {
        return $this->lists;
    }

    public function addList(TaskList $list): self
    {
        if (!$this->lists->contains($list)) {
            $this->lists->add($list);
            $list->setBoard($this);
        }

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
