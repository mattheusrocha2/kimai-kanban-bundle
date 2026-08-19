<?php

namespace KimaiPlugin\KanbanBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use KimaiPlugin\KanbanBundle\Repository\TaskListRepository;

/**
 * A column on the Kanban board / a "sublista" grouping tasks (e.g. "A Fazer",
 * "Em andamento", "Concluído" in Trello terms).
 */
#[ORM\Entity(repositoryClass: TaskListRepository::class)]
#[ORM\Table(name: 'kimai_kanban_lists')]
class TaskList
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Board::class, inversedBy: 'lists')]
    #[ORM\JoinColumn(name: 'board_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Board $board = null;

    #[ORM\Column(name: 'title', type: 'string', length: 150)]
    private string $title;

    #[ORM\Column(name: 'position', type: 'integer')]
    private int $position = 0;

    /**
     * Optional accent color for the whole column header (hex, e.g. "#eb5a46"),
     * used to flag a list as e.g. "Urgente".
     */
    #[ORM\Column(name: 'color', type: 'string', length: 7, nullable: true)]
    private ?string $color = null;

    #[ORM\OneToMany(mappedBy: 'taskList', targetEntity: Task::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $tasks;

    public function __construct()
    {
        $this->tasks = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBoard(): ?Board
    {
        return $this->board;
    }

    public function setBoard(Board $board): self
    {
        $this->board = $board;

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

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): self
    {
        $this->color = $color;

        return $this;
    }

    /**
     * @return Collection<int, Task>
     */
    public function getTasks(): Collection
    {
        return $this->tasks;
    }

    public function addTask(Task $task): self
    {
        if (!$this->tasks->contains($task)) {
            $this->tasks->add($task);
            $task->setTaskList($this);
        }

        return $this;
    }
}
