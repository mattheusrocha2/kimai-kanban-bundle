<?php

namespace KimaiPlugin\KanbanBundle\Controller;

use App\Controller\AbstractController;
use App\Entity\Activity;
use App\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;
use KimaiPlugin\KanbanBundle\Entity\Board;
use KimaiPlugin\KanbanBundle\Entity\TaskList;
use KimaiPlugin\KanbanBundle\Repository\BoardRepository;
use KimaiPlugin\KanbanBundle\Repository\TaskListRepository;
use KimaiPlugin\KanbanBundle\Repository\TaskRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/kanban')]
#[IsGranted('view_kanban')]
class BoardController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly BoardRepository $boardRepository,
        private readonly TaskListRepository $taskListRepository,
        private readonly TaskRepository $taskRepository,
    ) {
    }

    /**
     * Landing page: pick which project's board to open.
     */
    #[Route(path: '/', name: 'kanban_projects', methods: ['GET'])]
    public function projects(): Response
    {
        $projects = $this->entityManager->getRepository(Project::class)->findBy(['visible' => true], ['name' => 'ASC']);

        return $this->render('@Kanban/board/projects.html.twig', [
            'projects' => $projects,
        ]);
    }

    /**
     * Opens (or lazily creates) the board for a project and shows the
     * Kanban view.
     */
    #[Route(path: '/project/{project}', name: 'kanban_project_board', methods: ['GET'])]
    public function projectBoard(Project $project): Response
    {
        $board = $this->getOrCreateBoard($project);

        return $this->render('@Kanban/board/kanban.html.twig', [
            'board' => $board,
        ]);
    }

    /**
     * Same data as the Kanban view, but as a flat, sortable task list
     * (table), grouped by list — this is the "lista de tarefas" mode.
     */
    #[Route(path: '/project/{project}/list', name: 'kanban_project_list', methods: ['GET'])]
    public function projectList(Project $project): Response
    {
        $board = $this->getOrCreateBoard($project);
        $tasks = $this->taskRepository->findByBoard($board);

        return $this->render('@Kanban/board/list.html.twig', [
            'board' => $board,
            'tasks' => $tasks,
        ]);
    }

    #[Route(path: '/board/{board}/list/create', name: 'kanban_list_create', methods: ['POST'])]
    #[IsGranted('edit_kanban')]
    public function createList(Board $board, Request $request): Response
    {
        $title = trim((string) $request->request->get('title'));
        if ($title === '') {
            return $this->json(['error' => 'kanban.list.title_required'], 422);
        }

        $list = new TaskList();
        $list->setTitle($title);
        $list->setPosition($this->taskListRepository->getNextPosition($board));
        $board->addList($list);

        $this->entityManager->persist($list);
        $this->entityManager->flush();

        return $this->json(['id' => $list->getId(), 'title' => $list->getTitle()]);
    }

    #[Route(path: '/list/{list}/rename', name: 'kanban_list_rename', methods: ['POST'])]
    #[IsGranted('edit_kanban')]
    public function renameList(TaskList $list, Request $request): Response
    {
        $title = trim((string) $request->request->get('title'));
        if ($title === '') {
            return $this->json(['error' => 'kanban.list.title_required'], 422);
        }

        $list->setTitle($title);
        $this->entityManager->flush();

        return $this->json(['id' => $list->getId(), 'title' => $list->getTitle()]);
    }

    #[Route(path: '/list/{list}/color', name: 'kanban_list_color', methods: ['POST'])]
    #[IsGranted('edit_kanban')]
    public function colorList(TaskList $list, Request $request): Response
    {
        $color = (string) $request->request->get('color');
        if ($color !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            return $this->json(['error' => 'kanban.list.invalid_color'], 422);
        }

        $list->setColor($color === '' ? null : $color);
        $this->entityManager->flush();

        return $this->json(['id' => $list->getId(), 'color' => $list->getColor()]);
    }

    /**
     * Drag & drop: reorders a column within its board. Reindexes every list
     * on the board so the drop order survives a reload, instead of leaving
     * colliding position numbers (same fix as task reordering needed).
     */
    #[Route(path: '/list/{list}/reorder', name: 'kanban_list_reorder', methods: ['POST'])]
    #[IsGranted('edit_kanban')]
    public function reorderList(TaskList $list, Request $request): Response
    {
        $position = (int) $request->request->get('position', 0);
        $board = $list->getBoard();

        $siblings = array_values(array_filter(
            $board->getLists()->toArray(),
            static fn (TaskList $l) => $l->getId() !== $list->getId()
        ));

        $position = max(0, min($position, count($siblings)));
        array_splice($siblings, $position, 0, [$list]);

        foreach ($siblings as $index => $sibling) {
            $sibling->setPosition($index);
        }

        $this->entityManager->flush();

        return $this->json(['success' => true]);
    }

    #[Route(path: '/list/{list}/delete', name: 'kanban_list_delete', methods: ['POST'])]
    #[IsGranted('delete_kanban')]
    public function deleteList(TaskList $list): Response
    {
        $this->entityManager->remove($list);
        $this->entityManager->flush();

        return $this->json(['success' => true]);
    }

    private function getOrCreateBoard(Project $project): Board
    {
        $board = $this->boardRepository->findByProject($project);
        if ($board !== null) {
            return $board;
        }

        $board = new Board();
        $board->setProject($project);
        $board->setTitle($project->getName() ?? 'Kanban');

        // Dedicated activity so every timer started from this board's tasks
        // books time against the project through Kimai's normal timesheet.
        $activity = new Activity();
        $activity->setName('Kanban: ' . ($project->getName() ?? $board->getTitle()));
        $activity->setProject($project);
        $board->setActivity($activity);
        $this->entityManager->persist($activity);

        // Default Trello-like columns for a fresh board.
        foreach (['A Fazer', 'Em Andamento', 'Concluído'] as $position => $title) {
            $list = new TaskList();
            $list->setTitle($title);
            $list->setPosition($position);
            $board->addList($list);
        }

        $this->entityManager->persist($board);
        $this->entityManager->flush();

        return $board;
    }
}
