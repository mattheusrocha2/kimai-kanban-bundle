<?php

namespace KimaiPlugin\KanbanBundle\Entity;

use App\Entity\Timesheet;
use App\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use KimaiPlugin\KanbanBundle\Repository\TaskRepository;

/**
 * A card: one task, with description, dates, checklist ("sublista") and
 * links to the Kimai Timesheet entries tracked against it.
 */
#[ORM\Entity(repositoryClass: TaskRepository::class)]
#[ORM\Table(name: 'kimai_kanban_tasks')]
class Task
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: TaskList::class, inversedBy: 'tasks')]
    #[ORM\JoinColumn(name: 'list_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?TaskList $taskList = null;

    #[ORM\Column(name: 'title', type: 'string', length: 255)]
    private string $title;

    #[ORM\Column(name: 'description', type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'position', type: 'integer')]
    private int $position = 0;

    #[ORM\Column(name: 'start_date', type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $startDate = null;

    #[ORM\Column(name: 'due_date', type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dueDate = null;

    #[ORM\Column(name: 'end_date', type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $endDate = null;

    #[ORM\Column(name: 'is_done', type: 'boolean')]
    private bool $isDone = false;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /**
     * The Timesheet entry currently running for this task (set by "iniciar",
     * cleared by "pausar"). Null means the task's timer is stopped.
     */
    #[ORM\ManyToOne(targetEntity: Timesheet::class)]
    #[ORM\JoinColumn(name: 'active_timesheet_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Timesheet $activeTimesheet = null;

    /**
     * Every Timesheet entry (running or stopped) ever tracked against this
     * task, so worked time is simply the sum of their durations.
     */
    #[ORM\ManyToMany(targetEntity: Timesheet::class)]
    #[ORM\JoinTable(name: 'kimai_kanban_task_timesheets')]
    #[ORM\JoinColumn(name: 'task_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'timesheet_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $timesheets;

    #[ORM\OneToMany(mappedBy: 'task', targetEntity: ChecklistItem::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $checklistItems;

    public function __construct()
    {
        $this->timesheets = new ArrayCollection();
        $this->checklistItems = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTaskList(): ?TaskList
    {
        return $this->taskList;
    }

    public function setTaskList(TaskList $taskList): self
    {
        $this->taskList = $taskList;

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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

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

    public function getStartDate(): ?\DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTimeImmutable $startDate): self
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getDueDate(): ?\DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function setDueDate(?\DateTimeImmutable $dueDate): self
    {
        $this->dueDate = $dueDate;

        return $this;
    }

    public function getEndDate(): ?\DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeImmutable $endDate): self
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function isDone(): bool
    {
        return $this->isDone;
    }

    public function setIsDone(bool $isDone): self
    {
        $this->isDone = $isDone;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): self
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getActiveTimesheet(): ?Timesheet
    {
        return $this->activeTimesheet;
    }

    public function setActiveTimesheet(?Timesheet $timesheet): self
    {
        $this->activeTimesheet = $timesheet;

        return $this;
    }

    public function isRunning(): bool
    {
        return $this->activeTimesheet !== null;
    }

    /**
     * @return Collection<int, Timesheet>
     */
    public function getTimesheets(): Collection
    {
        return $this->timesheets;
    }

    public function addTimesheet(Timesheet $timesheet): self
    {
        if (!$this->timesheets->contains($timesheet)) {
            $this->timesheets->add($timesheet);
        }

        return $this;
    }

    /**
     * Total worked time in seconds, summed over every timesheet entry
     * (stopped ones use their stored duration; the running one, if any, is
     * computed live from "begin" until now).
     */
    public function getTotalWorkedSeconds(): int
    {
        $total = 0;
        foreach ($this->timesheets as $timesheet) {
            if ($timesheet->getEnd() !== null) {
                $total += (int) $timesheet->getDuration();
            } elseif ($timesheet->getBegin() !== null) {
                $total += (new \DateTimeImmutable())->getTimestamp() - $timesheet->getBegin()->getTimestamp();
            }
        }

        return $total;
    }

    /**
     * @return Collection<int, ChecklistItem>
     */
    public function getChecklistItems(): Collection
    {
        return $this->checklistItems;
    }

    public function addChecklistItem(ChecklistItem $item): self
    {
        if (!$this->checklistItems->contains($item)) {
            $this->checklistItems->add($item);
            $item->setTask($this);
        }

        return $this;
    }

    public function getChecklistProgress(): array
    {
        $total = $this->checklistItems->count();
        $done = 0;
        foreach ($this->checklistItems as $item) {
            if ($item->isChecked()) {
                $done++;
            }
        }

        return ['done' => $done, 'total' => $total];
    }
}
