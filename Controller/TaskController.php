<?php

namespace KimaiPlugin\KanbanBundle\Controller;

use App\Controller\AbstractController;
use App\Entity\Timesheet;
use Doctrine\ORM\EntityManagerInterface;
use KimaiPlugin\KanbanBundle\Entity\ChecklistItem;
use KimaiPlugin\KanbanBundle\Entity\Task;
use KimaiPlugin\KanbanBundle\Entity\TaskAttachment;
use KimaiPlugin\KanbanBundle\Entity\TaskList;
use KimaiPlugin\KanbanBundle\Repository\ChecklistItemRepository;
use KimaiPlugin\KanbanBundle\Repository\TaskRepository;
use KimaiPlugin\KanbanBundle\Service\TaskAttachmentStorage;
use KimaiPlugin\KanbanBundle\Service\TaskTimeTrackingService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/kanban')]
#[IsGranted('view_kanban')]
class TaskController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TaskRepository $taskRepository,
        private readonly ChecklistItemRepository $checklistItemRepository,
        private readonly TaskTimeTrackingService $timeTracking,
        private readonly TaskAttachmentStorage $attachmentStorage,
    ) {
    }

    #[Route(path: '/list/{list}/task/create', name: 'kanban_task_create', methods: ['POST'])]
    #[IsGranted('create_kanban')]
    public function create(TaskList $list, Request $request): Response
    {
        $title = trim((string) $request->request->get('title'));
        if ($title === '') {
            return $this->json(['error' => 'kanban.task.title_required'], 422);
        }

        $task = new Task();
        $task->setTitle($title);
        $task->setPosition($this->taskRepository->getNextPosition($list));
        $task->setCreatedBy($this->getUser());
        $list->addTask($task);

        $this->entityManager->persist($task);
        $this->entityManager->flush();

        return $this->json($this->serializeTask($task));
    }

    #[Route(path: '/task/{task}', name: 'kanban_task_show', methods: ['GET'])]
    public function show(Task $task): Response
    {
        return $this->json($this->serializeTask($task, true));
    }

    #[Route(path: '/task/{task}/update', name: 'kanban_task_update', methods: ['POST'])]
    #[IsGranted('edit_kanban')]
    public function update(Task $task, Request $request): Response
    {
        if ($request->request->has('title')) {
            $title = trim((string) $request->request->get('title'));
            if ($title === '') {
                return $this->json(['error' => 'kanban.task.title_required'], 422);
            }
            $task->setTitle($title);
        }

        if ($request->request->has('description')) {
            $task->setDescription($request->request->get('description') ?: null);
        }

        foreach (['startDate' => 'setStartDate', 'dueDate' => 'setDueDate', 'endDate' => 'setEndDate'] as $field => $setter) {
            if ($request->request->has($field)) {
                $value = $request->request->get($field);
                $task->{$setter}($value ? new \DateTimeImmutable($value) : null);
            }
        }

        if ($request->request->has('isDone')) {
            $task->setIsDone((bool) $request->request->get('isDone'));
        }

        $this->entityManager->flush();

        return $this->json($this->serializeTask($task, true));
    }

    /**
     * Drag & drop: moves a task to another list and/or reorders it within
     * its list (including its own list, to just reorder cards). Reindexes
     * every task in the target list's positions so the drop order actually
     * survives a reload, instead of leaving colliding position numbers.
     */
    #[Route(path: '/task/{task}/move', name: 'kanban_task_move', methods: ['POST'])]
    #[IsGranted('edit_kanban')]
    public function move(Task $task, Request $request, EntityManagerInterface $em): Response
    {
        $listId = $request->request->get('listId');
        $position = (int) $request->request->get('position', 0);
        $sourceList = $task->getTaskList();

        $targetList = $sourceList;
        if ($listId !== null) {
            $list = $em->getRepository(TaskList::class)->find($listId);
            if ($list === null || $list->getBoard()->getId() !== $sourceList->getBoard()->getId()) {
                return $this->json(['error' => 'kanban.list.not_found'], 404);
            }
            $targetList = $list;
        }

        // Every other task currently in the target list, in their existing
        // order, with the dragged task removed if it was already there.
        $siblings = array_values(array_filter(
            $targetList->getTasks()->toArray(),
            static fn (Task $t) => $t->getId() !== $task->getId()
        ));

        $position = max(0, min($position, count($siblings)));
        array_splice($siblings, $position, 0, [$task]);

        foreach ($siblings as $index => $sibling) {
            $sibling->setPosition($index);
        }

        $task->setTaskList($targetList);

        $this->entityManager->flush();

        return $this->json(['success' => true]);
    }

    #[Route(path: '/task/{task}/start', name: 'kanban_task_start', methods: ['POST'])]
    #[IsGranted('edit_kanban')]
    public function start(Task $task): Response
    {
        try {
            $this->timeTracking->start($task, $this->getUser());
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }

        return $this->json($this->serializeTask($task, true));
    }

    #[Route(path: '/task/{task}/pause', name: 'kanban_task_pause', methods: ['POST'])]
    #[IsGranted('edit_kanban')]
    public function pause(Task $task): Response
    {
        $this->timeTracking->pause($task);

        return $this->json($this->serializeTask($task, true));
    }

    /**
     * Manually logs a finished work block (e.g. the user forgot to press
     * "start" and only remembers at the end: "worked from 10:00 to 13:00").
     */
    #[Route(path: '/task/{task}/log-time', name: 'kanban_task_log_time', methods: ['POST'])]
    #[IsGranted('edit_kanban')]
    public function logTime(Task $task, Request $request): Response
    {
        $date = (string) $request->request->get('date');
        $startTime = (string) $request->request->get('startTime');
        $endTime = (string) $request->request->get('endTime');

        if ($date === '' || $startTime === '' || $endTime === '') {
            return $this->json(['error' => 'kanban.task.log.fields_required'], 422);
        }

        try {
            $begin = new \DateTime($date . ' ' . $startTime);
            $end = new \DateTime($date . ' ' . $endTime);

            // Overnight shift: if the end time is not after the begin time,
            // the work block must have rolled over into the next day
            // (e.g. started 22:23, ended 01:40).
            if ($end <= $begin) {
                $end->modify('+1 day');
            }

            $this->timeTracking->logManual($task, $this->getUser(), $begin, $end);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        } catch (\Exception) {
            return $this->json(['error' => 'kanban.task.log.invalid_time'], 422);
        }

        return $this->json($this->serializeTask($task, true));
    }

    #[Route(path: '/task/{task}/log/{timesheet}/delete', name: 'kanban_task_log_delete', methods: ['POST'])]
    #[IsGranted('edit_kanban')]
    public function deleteLog(Task $task, Timesheet $timesheet): Response
    {
        if (!$task->getTimesheets()->contains($timesheet)) {
            return $this->json(['error' => 'kanban.task.log.not_found'], 404);
        }

        try {
            $this->timeTracking->deleteLog($task, $timesheet);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }

        return $this->json($this->serializeTask($task, true));
    }

    #[Route(path: '/task/{task}/delete', name: 'kanban_task_delete', methods: ['POST'])]
    #[IsGranted('delete_kanban')]
    public function delete(Task $task): Response
    {
        $this->entityManager->remove($task);
        $this->entityManager->flush();

        return $this->json(['success' => true]);
    }

    #[Route(path: '/task/{task}/checklist/create', name: 'kanban_checklist_create', methods: ['POST'])]
    #[IsGranted('edit_kanban')]
    public function createChecklistItem(Task $task, Request $request): Response
    {
        $text = trim((string) $request->request->get('text'));
        if ($text === '') {
            return $this->json(['error' => 'kanban.checklist.text_required'], 422);
        }

        $item = new ChecklistItem();
        $item->setText($text);
        $item->setPosition($this->checklistItemRepository->getNextPosition($task));
        $task->addChecklistItem($item);

        $this->entityManager->persist($item);
        $this->entityManager->flush();

        return $this->json([
            'id' => $item->getId(),
            'text' => $item->getText(),
            'checked' => $item->isChecked(),
            'progress' => $task->getChecklistProgress(),
        ]);
    }

    #[Route(path: '/checklist-item/{item}/child/create', name: 'kanban_checklist_child_create', methods: ['POST'])]
    #[IsGranted('edit_kanban')]
    public function createChecklistChildItem(ChecklistItem $item, Request $request): Response
    {
        $text = trim((string) $request->request->get('text'));
        if ($text === '') {
            return $this->json(['error' => 'kanban.checklist.text_required'], 422);
        }

        $task = $item->getTask();

        $child = new ChecklistItem();
        $child->setText($text);
        $child->setPosition($this->checklistItemRepository->getNextPosition($task, $item));
        $item->addChild($child);
        $task->addChecklistItem($child);

        $this->entityManager->persist($child);
        $this->entityManager->flush();

        return $this->json([
            'id' => $child->getId(),
            'text' => $child->getText(),
            'checked' => $child->isChecked(),
            'parentId' => $item->getId(),
            'progress' => $task->getChecklistProgress(),
        ]);
    }

    #[Route(path: '/checklist-item/{item}/toggle', name: 'kanban_checklist_toggle', methods: ['POST'])]
    #[IsGranted('edit_kanban')]
    public function toggleChecklistItem(ChecklistItem $item): Response
    {
        $item->setChecked(!$item->isChecked());
        $this->entityManager->flush();

        return $this->json([
            'id' => $item->getId(),
            'checked' => $item->isChecked(),
            'progress' => $item->getTask()->getChecklistProgress(),
        ]);
    }

    #[Route(path: '/checklist-item/{item}/delete', name: 'kanban_checklist_delete', methods: ['POST'])]
    #[IsGranted('edit_kanban')]
    public function deleteChecklistItem(ChecklistItem $item): Response
    {
        $task = $item->getTask();
        $this->entityManager->remove($item);
        $this->entityManager->flush();

        return $this->json(['success' => true, 'progress' => $task->getChecklistProgress()]);
    }

    /**
     * One file per request — the front-end loops over multi-select/paste and
     * fires this once per image, which keeps validation and error reporting
     * per-file instead of all-or-nothing.
     */
    #[Route(path: '/task/{task}/attachment/upload', name: 'kanban_attachment_upload', methods: ['POST'])]
    #[IsGranted('edit_kanban')]
    public function uploadAttachment(Task $task, Request $request): Response
    {
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            return $this->json(['error' => 'kanban.attachment.invalid_file'], 422);
        }

        if (!\in_array($file->getMimeType(), TaskAttachmentStorage::ALLOWED_MIME_TYPES, true)) {
            return $this->json(['error' => 'kanban.attachment.unsupported_type'], 422);
        }

        if ($file->getSize() > TaskAttachmentStorage::MAX_SIZE_BYTES) {
            return $this->json(['error' => 'kanban.attachment.too_large'], 422);
        }

        $originalName = $file->getClientOriginalName();
        $mimeType = $file->getMimeType();
        $size = $file->getSize();

        try {
            $filename = $this->attachmentStorage->save($file);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }

        $attachment = new TaskAttachment();
        $attachment->setFilename($filename);
        $attachment->setOriginalName($originalName);
        $attachment->setMimeType($mimeType);
        $attachment->setSize($size);
        $attachment->setUploadedBy($this->getUser());
        $task->addAttachment($attachment);

        $this->entityManager->persist($attachment);
        $this->entityManager->flush();

        return $this->json($this->serializeAttachment($attachment));
    }

    #[Route(path: '/attachment/{attachment}/file', name: 'kanban_attachment_file', methods: ['GET'])]
    public function attachmentFile(TaskAttachment $attachment): Response
    {
        $path = $this->attachmentStorage->getPath($attachment->getFilename());
        if (!is_file($path)) {
            throw $this->createNotFoundException();
        }

        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', $attachment->getMimeType());
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $attachment->getOriginalName());

        return $response;
    }

    #[Route(path: '/attachment/{attachment}/delete', name: 'kanban_attachment_delete', methods: ['POST'])]
    #[IsGranted('edit_kanban')]
    public function deleteAttachment(TaskAttachment $attachment): Response
    {
        $this->attachmentStorage->delete($attachment->getFilename());
        $this->entityManager->remove($attachment);
        $this->entityManager->flush();

        return $this->json(['success' => true]);
    }

    private function serializeAttachment(TaskAttachment $attachment): array
    {
        return [
            'id' => $attachment->getId(),
            'url' => $this->generateUrl('kanban_attachment_file', ['attachment' => $attachment->getId()]),
            'originalName' => $attachment->getOriginalName(),
            'mimeType' => $attachment->getMimeType(),
            'size' => $attachment->getSize(),
        ];
    }

    private function serializeTask(Task $task, bool $withChecklist = false): array
    {
        $data = [
            'id' => $task->getId(),
            'title' => $task->getTitle(),
            'description' => $task->getDescription(),
            'listId' => $task->getTaskList()->getId(),
            'position' => $task->getPosition(),
            'startDate' => $task->getStartDate()?->format('Y-m-d'),
            'dueDate' => $task->getDueDate()?->format('Y-m-d'),
            'endDate' => $task->getEndDate()?->format('Y-m-d'),
            'isDone' => $task->isDone(),
            'isRunning' => $task->isRunning(),
            'totalWorkedSeconds' => $task->getTotalWorkedSeconds(),
            'checklistProgress' => $task->getChecklistProgress(),
        ];

        if ($withChecklist) {
            // array_values() matters here: array_filter() keeps the original,
            // non-sequential keys, and array_map() carries them through — so
            // json_encode() would serialize this as a JSON *object* instead
            // of an array the moment any child item existed (e.g. {"0":...,
            // "2":...}), breaking the front-end's items.forEach().
            $topLevel = array_values(array_filter(
                $task->getChecklistItems()->toArray(),
                static fn (ChecklistItem $i) => $i->getParent() === null
            ));

            $serializeItem = static fn (ChecklistItem $i) => [
                'id' => $i->getId(),
                'text' => $i->getText(),
                'checked' => $i->isChecked(),
                'children' => array_values(array_map(static fn (ChecklistItem $c) => [
                    'id' => $c->getId(),
                    'text' => $c->getText(),
                    'checked' => $c->isChecked(),
                ], $i->getChildren()->toArray())),
            ];

            $data['checklist'] = array_map($serializeItem, $topLevel);

            $logs = array_map(static fn (Timesheet $t) => [
                'id' => $t->getId(),
                'begin' => $t->getBegin()?->format('Y-m-d\TH:i'),
                'end' => $t->getEnd()?->format('Y-m-d\TH:i'),
                'running' => $t->getEnd() === null,
                'duration' => $t->getEnd() !== null ? (int) $t->getDuration() : null,
            ], $task->getTimesheets()->toArray());
            usort($logs, static fn (array $a, array $b) => $b['begin'] <=> $a['begin']);
            $data['timeLog'] = $logs;

            $data['attachments'] = array_map(
                fn (TaskAttachment $a) => $this->serializeAttachment($a),
                $task->getAttachments()->toArray()
            );
        }

        return $data;
    }
}
