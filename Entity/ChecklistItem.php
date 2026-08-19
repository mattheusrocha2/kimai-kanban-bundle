<?php

namespace KimaiPlugin\KanbanBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use KimaiPlugin\KanbanBundle\Repository\ChecklistItemRepository;

/**
 * One line of a task's checklist ("sublista"), Trello-style: free text + done flag.
 */
#[ORM\Entity(repositoryClass: ChecklistItemRepository::class)]
#[ORM\Table(name: 'kimai_kanban_checklist_items')]
class ChecklistItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Task::class, inversedBy: 'checklistItems')]
    #[ORM\JoinColumn(name: 'task_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Task $task = null;

    #[ORM\Column(name: 'text', type: 'string', length: 500)]
    private string $text;

    #[ORM\Column(name: 'is_checked', type: 'boolean')]
    private bool $checked = false;

    #[ORM\Column(name: 'position', type: 'integer')]
    private int $position = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTask(): ?Task
    {
        return $this->task;
    }

    public function setTask(Task $task): self
    {
        $this->task = $task;

        return $this;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function setText(string $text): self
    {
        $this->text = $text;

        return $this;
    }

    public function isChecked(): bool
    {
        return $this->checked;
    }

    public function setChecked(bool $checked): self
    {
        $this->checked = $checked;

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
}
