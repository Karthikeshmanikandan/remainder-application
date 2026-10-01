<?php

namespace App\Http\Controllers;

use App\Enums\ReminderStatus;
use App\Http\Requests\StoreTaskReminderRequest;
use App\Models\Task;
use App\Models\TaskReminder;
use App\Models\User;
use App\Services\TaskReminderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TaskReminderController extends Controller
{
    public function __construct(private readonly TaskReminderService $reminderService) {}

    /**
     * Show all reminders for the authenticated user (upcoming, triggered, cancelled).
     */
    public function index(Request $request): View
    {
        $user = auth()->user();

        $query = TaskReminder::with(['task.project'])
            ->where('user_id', $user->id);

        // Admins can also see all if they choose — for now show own
        $upcomingReminders = (clone $query)
            ->where('status', ReminderStatus::PENDING)
            ->orderBy('remind_at')
            ->get();

        $triggeredReminders = (clone $query)
            ->where('status', ReminderStatus::TRIGGERED)
            ->orderByDesc('triggered_at')
            ->get();

        $cancelledReminders = (clone $query)
            ->where('status', ReminderStatus::CANCELLED)
            ->orderByDesc('updated_at')
            ->get();

        return view('reminders.index', compact('upcomingReminders', 'triggeredReminders', 'cancelledReminders'));
    }

    /**
     * Store a new reminder for a task.
     * POST /tasks/{task}/reminders
     */
    public function store(StoreTaskReminderRequest $request, Task $task): RedirectResponse
    {
        $actor = auth()->user();

        // Determine the recipient — only admin/manager can set any user; employees default to self
        $recipientId = $request->integer('user_id');
        if ($actor->isEmployee() && $recipientId !== $actor->id) {
            abort(403, 'Employees can only create reminders for themselves.');
        }

        // Make sure the recipient user actually exists (already validated, but verify)
        $recipient = User::findOrFail($recipientId);

        TaskReminder::create([
            'task_id' => $task->id,
            'user_id' => $recipient->id,
            'remind_at' => $request->remind_at,
            'status' => ReminderStatus::PENDING,
        ]);

        return redirect()
            ->route('tasks.show', $task)
            ->with('success', 'Reminder set successfully.');
    }

    /**
     * Cancel a pending reminder.
     * DELETE /tasks/{task}/reminders/{reminder}
     */
    public function destroy(Task $task, TaskReminder $reminder): RedirectResponse
    {
        // Ownership check — only admin or the reminder's recipient can cancel
        $actor = auth()->user();
        if (! $actor->isAdmin() && $reminder->user_id !== $actor->id) {
            abort(403, 'You are not authorised to cancel this reminder.');
        }

        // Verify the reminder belongs to this task (IDOR protection)
        if ($reminder->task_id !== $task->id) {
            abort(404);
        }

        if (! $this->reminderService->cancelReminder($reminder)) {
            return redirect()
                ->route('tasks.show', $task)
                ->with('error', 'Only pending reminders can be cancelled.');
        }

        return redirect()
            ->route('tasks.show', $task)
            ->with('success', 'Reminder cancelled.');
    }
}
