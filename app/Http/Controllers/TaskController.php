<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\TelegramEmployee;
use App\Models\User;
use App\Services\TaskService;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function __construct(
        private TaskService $taskService
    ) {}

    public function index(Request $request)
    {
        $orgId = auth()->user()->organization_id ?? 1;
        $query = Task::with(['project', 'assignee', 'assignedTelegramEmployee'])
            ->where('organization_id', $orgId);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }
        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        $tasks = $query->latest()->paginate(10);
        $projects = Project::where('organization_id', $orgId)->orderBy('name')->get();

        return view('tasks.index', compact('tasks', 'projects'));
    }

    public function create()
    {
        $orgId = auth()->user()->organization_id ?? 1;
        $projects = Project::where('organization_id', $orgId)->orderBy('name')->get();
        $users = User::where('organization_id', $orgId)->orderBy('name')->get();
        $telegramEmployees = TelegramEmployee::where('organization_id', $orgId)->where('is_active', true)->orderBy('name')->get();

        return view('tasks.create', compact('projects', 'users', 'telegramEmployees'));
    }

    public function store(StoreTaskRequest $request)
    {
        $orgId = auth()->user()->organization_id ?? 1;
        $data = array_merge($request->validated(), [
            'organization_id' => $orgId,
            'created_by' => auth()->id(),
        ]);

        $task = $this->taskService->createTask($data, auth()->user());

        return redirect()->route('tasks.index')->with('success', 'Task created successfully.');
    }

    public function show(Task $task)
    {
        $this->ensureSameOrganization($task);

        $task->load(['project', 'assignee', 'assignedTelegramEmployee', 'creator', 'reminders.user']);
        $orgId = auth()->user()->organization_id ?? 1;
        $users = User::where('organization_id', $orgId)->orderBy('name')->get();

        return view('tasks.show', compact('task', 'users'));
    }

    public function edit(Task $task)
    {
        $this->ensureSameOrganization($task);

        $orgId = auth()->user()->organization_id ?? 1;
        $projects = Project::where('organization_id', $orgId)->orderBy('name')->get();
        $users = User::where('organization_id', $orgId)->orderBy('name')->get();
        $telegramEmployees = TelegramEmployee::where('organization_id', $orgId)->where('is_active', true)->orderBy('name')->get();

        return view('tasks.edit', compact('task', 'projects', 'users', 'telegramEmployees'));
    }

    public function update(UpdateTaskRequest $request, Task $task)
    {
        $this->ensureSameOrganization($task);

        $this->taskService->updateTask($task, $request->validated());

        return redirect()->route('tasks.index')->with('success', 'Task updated successfully.');
    }

    public function destroy(Task $task)
    {
        $this->ensureSameOrganization($task);

        $task->delete();

        return redirect()->route('tasks.index')->with('success', 'Task deleted successfully.');
    }

    private function ensureSameOrganization(Task $task): void
    {
        if (auth()->check() && auth()->user()->organization_id && $task->organization_id) {
            if (auth()->user()->organization_id !== $task->organization_id) {
                abort(403, 'Unauthorized action for this organization.');
            }
        }
    }
}
