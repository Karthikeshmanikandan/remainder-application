<?php

namespace App\Http\Controllers;

use App\Enums\RecurringTaskStatus;
use App\Http\Requests\StoreRecurringTaskRequest;
use App\Http\Requests\UpdateRecurringTaskRequest;
use App\Models\Project;
use App\Models\RecurringTask;
use App\Models\User;
use App\Services\RecurringTaskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RecurringTaskController extends Controller
{
    public function __construct(private readonly RecurringTaskService $service) {}

    /**
     * Only Admins and Managers can manage recurring task definitions.
     */
    private function authorizeManagement(): void
    {
        if (auth()->user()->isEmployee()) {
            abort(403, 'Employees cannot manage recurring task definitions.');
        }
    }

    public function index(): View
    {
        $user = auth()->user();

        $query = RecurringTask::with(['project', 'assignee']);

        if ($user->isEmployee()) {
            // Employees see only their own assigned definitions
            $query->where('assigned_to', $user->id);
        }
        // Admins and Managers see all (Managers could be further scoped by project in future phases)

        $recurringTasks = $query->latest()->paginate(10);

        return view('recurring-tasks.index', compact('recurringTasks'));
    }

    public function create(): View
    {
        $this->authorizeManagement();

        $projects = Project::orderBy('name')->get();
        $users = User::orderBy('name')->get();

        return view('recurring-tasks.create', compact('projects', 'users'));
    }

    public function store(StoreRecurringTaskRequest $request): RedirectResponse
    {
        $this->authorizeManagement();

        $data = $request->validated();
        $data['created_by'] = auth()->id();
        $data['status'] = RecurringTaskStatus::ACTIVE;
        $data['auto_create_reminder'] = $request->boolean('auto_create_reminder');
        $data['reminder_offset_minutes'] = $request->input('reminder_offset_minutes', 60);

        // next_run_at starts at starts_at
        $data['next_run_at'] = $data['starts_at'];

        RecurringTask::create($data);

        return redirect()->route('recurring-tasks.index')
            ->with('success', 'Recurring task created successfully.');
    }

    public function show(RecurringTask $recurringTask): View
    {
        $recurringTask->load(['project', 'assignee', 'creator']);

        $recentOccurrences = $recurringTask->occurrences()
            ->with('task')
            ->latest()
            ->take(10)
            ->get();

        return view('recurring-tasks.show', compact('recurringTask', 'recentOccurrences'));
    }

    public function edit(RecurringTask $recurringTask): View
    {
        $this->authorizeManagement();

        $projects = Project::orderBy('name')->get();
        $users = User::orderBy('name')->get();

        return view('recurring-tasks.edit', compact('recurringTask', 'projects', 'users'));
    }

    public function update(UpdateRecurringTaskRequest $request, RecurringTask $recurringTask): RedirectResponse
    {
        $this->authorizeManagement();

        $data = $request->validated();
        $data['auto_create_reminder'] = $request->boolean('auto_create_reminder');
        $data['reminder_offset_minutes'] = $request->input('reminder_offset_minutes', 60);

        // Recalculate next_run_at if starts_at changed
        if ($recurringTask->starts_at->toDateString() !== now()->parse($data['starts_at'])->toDateString()) {
            $data['next_run_at'] = $data['starts_at'];
        }

        $recurringTask->update($data);

        return redirect()->route('recurring-tasks.show', $recurringTask)
            ->with('success', 'Recurring task updated.');
    }

    /**
     * Pause an active recurring task definition.
     */
    public function pause(RecurringTask $recurringTask): RedirectResponse
    {
        $this->authorizeManagement();

        if (! $recurringTask->isActive()) {
            return redirect()->back()->with('error', 'Only active definitions can be paused.');
        }

        $recurringTask->update(['status' => RecurringTaskStatus::PAUSED]);

        return redirect()->route('recurring-tasks.show', $recurringTask)
            ->with('success', 'Recurring task paused.');
    }

    /**
     * Resume a paused recurring task definition.
     */
    public function resume(RecurringTask $recurringTask): RedirectResponse
    {
        $this->authorizeManagement();

        if (! $recurringTask->isPaused()) {
            return redirect()->back()->with('error', 'Only paused definitions can be resumed.');
        }

        $recurringTask->update(['status' => RecurringTaskStatus::ACTIVE]);

        return redirect()->route('recurring-tasks.show', $recurringTask)
            ->with('success', 'Recurring task resumed.');
    }

    /**
     * Cancel a recurring task definition. Cannot be undone.
     */
    public function cancel(RecurringTask $recurringTask): RedirectResponse
    {
        $this->authorizeManagement();

        if ($recurringTask->isCancelled()) {
            return redirect()->back()->with('error', 'Already cancelled.');
        }

        $recurringTask->update(['status' => RecurringTaskStatus::CANCELLED]);

        return redirect()->route('recurring-tasks.show', $recurringTask)
            ->with('success', 'Recurring task cancelled. Existing generated tasks are not affected.');
    }
}
