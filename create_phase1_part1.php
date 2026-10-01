<?php

function create_file($path, $content)
{
    $dir = dirname(__DIR__.'/'.$path);
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents(__DIR__.'/'.$path, $content);
    echo "Created: $path\n";
}

// 1. DASHBOARD CONTROLLER
create_file('app/Http/Controllers/DashboardController.php', <<<'PHP'
<?php
namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;

class DashboardController extends Controller
{
    public function index()
    {
        $totalProjects = Project::count();
        $activeProjects = Project::where('status', ProjectStatus::ACTIVE)->count();
        
        $totalTasks = Task::count();
        $pendingTasks = Task::where('status', TaskStatus::PENDING)->count();
        $inProgressTasks = Task::where('status', TaskStatus::IN_PROGRESS)->count();
        $completedTasks = Task::where('status', TaskStatus::COMPLETED)->count();
        
        $overdueTasks = Task::where('due_date', '<', now())
            ->whereNotIn('status', [TaskStatus::COMPLETED, TaskStatus::CANCELLED])
            ->count();

        $recentTasks = Task::with('project')->latest()->take(5)->get();
        $recentProjects = Project::latest()->take(5)->get();

        return view('dashboard', compact(
            'totalProjects', 'activeProjects', 'totalTasks',
            'pendingTasks', 'inProgressTasks', 'completedTasks',
            'overdueTasks', 'recentTasks', 'recentProjects'
        ));
    }
}
PHP);

// 2. PROJECT CONTROLLER
create_file('app/Http/Controllers/ProjectController.php', <<<'PHP'
<?php
namespace App\Http\Controllers;

use App\Models\Project;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $query = Project::query();
        
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }
        
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        $projects = $query->withCount('tasks')->latest()->paginate(10);
        return view('projects.index', compact('projects'));
    }

    public function create()
    {
        return view('projects.create');
    }

    public function store(StoreProjectRequest $request)
    {
        Project::create($request->validated());
        return redirect()->route('projects.index')->with('success', 'Project created successfully.');
    }

    public function show(Project $project)
    {
        $project->load('tasks.assignee');
        return view('projects.show', compact('project'));
    }

    public function edit(Project $project)
    {
        return view('projects.edit', compact('project'));
    }

    public function update(UpdateProjectRequest $request, Project $project)
    {
        $project->update($request->validated());
        return redirect()->route('projects.index')->with('success', 'Project updated successfully.');
    }

    public function destroy(Project $project)
    {
        $project->delete();
        return redirect()->route('projects.index')->with('success', 'Project deleted successfully.');
    }
}
PHP);

// 3. TASK CONTROLLER
create_file('app/Http/Controllers/TaskController.php', <<<'PHP'
<?php
namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\Project;
use App\Models\User;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        $query = Task::with(['project', 'assignee']);
        
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
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
        $projects = Project::orderBy('name')->get();
        return view('tasks.index', compact('tasks', 'projects'));
    }

    public function create()
    {
        $projects = Project::orderBy('name')->get();
        $users = User::orderBy('name')->get();
        return view('tasks.create', compact('projects', 'users'));
    }

    public function store(StoreTaskRequest $request)
    {
        Task::create($request->validated());
        return redirect()->route('tasks.index')->with('success', 'Task created successfully.');
    }

    public function show(Task $task)
    {
        $task->load(['project', 'assignee', 'creator']);
        return view('tasks.show', compact('task'));
    }

    public function edit(Task $task)
    {
        $projects = Project::orderBy('name')->get();
        $users = User::orderBy('name')->get();
        return view('tasks.edit', compact('task', 'projects', 'users'));
    }

    public function update(UpdateTaskRequest $request, Task $task)
    {
        $task->update($request->validated());
        return redirect()->route('tasks.index')->with('success', 'Task updated successfully.');
    }

    public function destroy(Task $task)
    {
        $task->delete();
        return redirect()->route('tasks.index')->with('success', 'Task deleted successfully.');
    }
}
PHP);

// 4. REQUESTS
create_file('app/Http/Requests/StoreProjectRequest.php', <<<'PHP'
<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
use App\Enums\ProjectStatus;
use Illuminate\Validation\Rules\Enum;

class StoreProjectRequest extends FormRequest {
    public function authorize() { return true; }
    public function rules() {
        return [
            'code' => 'required|string|max:50|unique:projects,code',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => ['required', new Enum(ProjectStatus::class)],
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ];
    }
}
PHP);

create_file('app/Http/Requests/UpdateProjectRequest.php', <<<'PHP'
<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
use App\Enums\ProjectStatus;
use Illuminate\Validation\Rules\Enum;

class UpdateProjectRequest extends FormRequest {
    public function authorize() { return true; }
    public function rules() {
        return [
            'code' => 'required|string|max:50|unique:projects,code,' . $this->route('project')->id,
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => ['required', new Enum(ProjectStatus::class)],
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ];
    }
}
PHP);

create_file('app/Http/Requests/StoreTaskRequest.php', <<<'PHP'
<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
use App\Enums\TaskStatus;
use App\Enums\TaskPriority;
use Illuminate\Validation\Rules\Enum;

class StoreTaskRequest extends FormRequest {
    public function authorize() { return true; }
    public function rules() {
        return [
            'code' => 'required|string|max:50|unique:tasks,code',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'project_id' => 'required|exists:projects,id',
            'assigned_to' => 'nullable|exists:users,id',
            'priority' => ['required', new Enum(TaskPriority::class)],
            'status' => ['required', new Enum(TaskStatus::class)],
            'due_date' => 'nullable|date',
        ];
    }
}
PHP);

create_file('app/Http/Requests/UpdateTaskRequest.php', <<<'PHP'
<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
use App\Enums\TaskStatus;
use App\Enums\TaskPriority;
use Illuminate\Validation\Rules\Enum;

class UpdateTaskRequest extends FormRequest {
    public function authorize() { return true; }
    public function rules() {
        return [
            'code' => 'required|string|max:50|unique:tasks,code,' . $this->route('task')->id,
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'project_id' => 'required|exists:projects,id',
            'assigned_to' => 'nullable|exists:users,id',
            'priority' => ['required', new Enum(TaskPriority::class)],
            'status' => ['required', new Enum(TaskStatus::class)],
            'due_date' => 'nullable|date',
        ];
    }
}
PHP);

// 5. ROUTES
create_file('routes/web.php', <<<'PHP'
<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TaskController;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
Route::resource('projects', ProjectController::class);
Route::resource('tasks', TaskController::class);
PHP);

// SEEDERS
create_file('database/seeders/DatabaseSeeder.php', <<<'PHP'
<?php
namespace Database\Seeders;
use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Enums\TaskPriority;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('Password123!'),
        ]);

        $manager = User::factory()->create([
            'name' => 'Manager User',
            'email' => 'manager@example.com',
            'password' => Hash::make('Password123!'),
        ]);

        $employee = User::factory()->create([
            'name' => 'Employee User',
            'email' => 'employee@example.com',
            'password' => Hash::make('Password123!'),
        ]);

        $project = Project::create([
            'code' => 'PRJ-001',
            'name' => 'Alpha Development',
            'description' => 'Development of the new Alpha product.',
            'status' => ProjectStatus::ACTIVE,
            'created_by' => $admin->id,
            'start_date' => now(),
            'end_date' => now()->addMonths(3),
        ]);

        Task::create([
            'code' => 'TSK-001',
            'title' => 'Setup environment',
            'description' => 'Setup local dev environment',
            'project_id' => $project->id,
            'assigned_to' => $employee->id,
            'created_by' => $manager->id,
            'priority' => TaskPriority::HIGH,
            'status' => TaskStatus::IN_PROGRESS,
            'due_date' => now()->addDays(2),
        ]);
    }
}
PHP);
