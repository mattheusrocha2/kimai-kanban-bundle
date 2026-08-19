<?php

namespace KimaiPlugin\KanbanBundle\Service;

use App\Entity\Timesheet;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use KimaiPlugin\KanbanBundle\Entity\Board;
use KimaiPlugin\KanbanBundle\Entity\Task;

/**
 * Starts/pauses a task's timer by creating and stopping real Kimai
 * Timesheet entries, so tracked time shows up in Kimai's own reports,
 * exports and invoicing exactly like any other timesheet record.
 */
class TaskTimeTrackingService
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * @throws \RuntimeException if the user already has a running record, or the task has no activity to book against
     */
    public function start(Task $task, User $user): Timesheet
    {
        if ($task->isRunning()) {
            throw new \RuntimeException('kanban.task.already_running');
        }

        $board = $task->getTaskList()->getBoard();
        $activity = $board->getActivity();
        if ($activity === null) {
            throw new \RuntimeException('kanban.board.no_activity');
        }

        $existingRunning = $this->entityManager->getRepository(Timesheet::class)->findOneBy([
            'user' => $user,
            'end' => null,
        ]);
        if ($existingRunning !== null) {
            throw new \RuntimeException('kanban.task.other_record_running');
        }

        $timesheet = new Timesheet();
        $timesheet->setUser($user);
        $timesheet->setActivity($activity);
        $timesheet->setProject($board->getProject());
        $timesheet->setBegin(new \DateTime());
        $timesheet->setDescription($task->getTitle());

        $this->entityManager->persist($timesheet);

        $task->setActiveTimesheet($timesheet);
        $task->addTimesheet($timesheet);

        $this->entityManager->persist($task);
        $this->entityManager->flush();

        return $timesheet;
    }

    public function pause(Task $task): ?Timesheet
    {
        $timesheet = $task->getActiveTimesheet();
        if ($timesheet === null) {
            return null;
        }

        $end = new \DateTime();
        $timesheet->setEnd($end);
        $duration = $end->getTimestamp() - $timesheet->getBegin()->getTimestamp();
        $timesheet->setDuration($duration);

        $task->setActiveTimesheet(null);

        $this->entityManager->persist($timesheet);
        $this->entityManager->persist($task);
        $this->entityManager->flush();

        return $timesheet;
    }

    /**
     * Manually logs a past, already-finished work block (e.g. "worked from
     * 10:00 to 13:00 but forgot to press start"). Creates a stopped
     * Timesheet entry directly, same as start()+pause() would have produced.
     *
     * @throws \RuntimeException if the task has no activity to book against, or the times are invalid
     */
    public function logManual(Task $task, User $user, \DateTime $begin, \DateTime $end): Timesheet
    {
        if ($end <= $begin) {
            throw new \RuntimeException('kanban.task.log.end_before_begin');
        }

        $board = $task->getTaskList()->getBoard();
        $activity = $board->getActivity();
        if ($activity === null) {
            throw new \RuntimeException('kanban.board.no_activity');
        }

        $timesheet = new Timesheet();
        $timesheet->setUser($user);
        $timesheet->setActivity($activity);
        $timesheet->setProject($board->getProject());
        $timesheet->setBegin($begin);
        $timesheet->setEnd($end);
        $timesheet->setDuration($end->getTimestamp() - $begin->getTimestamp());
        $timesheet->setDescription($task->getTitle());

        $this->entityManager->persist($timesheet);

        $task->addTimesheet($timesheet);
        $this->entityManager->persist($task);
        $this->entityManager->flush();

        return $timesheet;
    }

    /**
     * Removes one logged time block from a task (e.g. to fix a mistaken
     * manual entry). Refuses to remove the currently running one — use
     * pause() for that.
     */
    public function deleteLog(Task $task, Timesheet $timesheet): void
    {
        if ($task->getActiveTimesheet() !== null && $task->getActiveTimesheet()->getId() === $timesheet->getId()) {
            throw new \RuntimeException('kanban.task.log.cannot_delete_running');
        }

        $task->getTimesheets()->removeElement($timesheet);
        $this->entityManager->remove($timesheet);
        $this->entityManager->flush();
    }
}
