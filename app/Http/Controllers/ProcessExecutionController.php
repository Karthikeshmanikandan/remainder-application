<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmProcessExecutionRequest;
use App\Http\Requests\SaveProcessExecutionProgressRequest;
use App\Models\Department;
use App\Models\Process;
use App\Models\ProcessExecution;
use App\Models\User;
use App\Services\ProcessAccountabilityService;
use App\Services\ProcessExecutionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProcessExecutionController extends Controller
{
    public function __construct(
        private ProcessExecutionService $executionService,
        private ProcessAccountabilityService $accountabilityService
    ) {}

    public function index(Request $request): View
    {
        $user = auth()->user();
        $orgId = $user->organization_id ?? 1;

        $query = ProcessExecution::with(['process.department', 'process.responsibleUser', 'process.responsibleTelegramEmployee', 'completedBy', 'completedByTelegramEmployee'])
            ->whereHas('process', fn ($q) => $q->where('organization_id', $orgId));

        // Scope to employee's own assigned processes
        if ($user->isEmployee()) {
            $query->forUser($user->id);
        } else {
            // Management filters
            if ($request->filled('department_id')) {
                $query->forDepartment($request->integer('department_id'));
            }

            if ($request->filled('responsible_user_id')) {
                $query->forUser($request->integer('responsible_user_id'));
            }
        }

        if ($request->filled('process_id')) {
            $query->where('process_id', $request->integer('process_id'));
        }

        // Date Range Filter
        if ($request->filled('date_range') && $request->date_range !== 'all') {
            $dateInfo = $this->accountabilityService->resolveDateRange(
                $request->date_range,
                $request->start_date,
                $request->end_date
            );
            $query->whereBetween('scheduled_for', [$dateInfo['start'], $dateInfo['end']]);
        }

        // Status Filter
        if ($request->filled('status') && $request->status !== 'all') {
            if ($request->status === 'overdue') {
                $query->overdue();
            } else {
                $query->where('status', $request->status);
            }
        }

        $executions = $query->latest('scheduled_for')->paginate(15)->withQueryString();

        $departments = ! $user->isEmployee() ? Department::where('organization_id', $orgId)->orderBy('name')->get() : collect();
        $processes = ! $user->isEmployee() ? Process::where('organization_id', $orgId)->orderBy('name')->get() : Process::where('organization_id', $orgId)->where('responsible_user_id', $user->id)->orderBy('name')->get();
        $users = ! $user->isEmployee() ? User::where('organization_id', $orgId)->orderBy('name')->get() : collect();

        return view('process-executions.index', compact('executions', 'departments', 'processes', 'users'));
    }

    public function show(ProcessExecution $execution): View
    {
        $user = auth()->user();
        $this->ensureSameOrganization($execution);

        // IDOR Authorization protection
        if ($user->isEmployee() && $execution->process->responsible_user_id !== $user->id) {
            abort(403, 'Unauthorized access to this checklist execution.');
        }

        $execution->load([
            'process.department',
            'process.responsibleUser',
            'process.responsibleTelegramEmployee',
            'completedBy',
            'completedByTelegramEmployee',
            'items' => fn ($q) => $q->join('process_items', 'process_execution_items.process_item_id', '=', 'process_items.id')
                ->orderBy('process_items.sort_order')
                ->select('process_execution_items.*'),
        ]);

        return view('process-executions.show', compact('execution'));
    }

    public function saveProgress(SaveProcessExecutionProgressRequest $request, ProcessExecution $execution): RedirectResponse
    {
        $this->ensureSameOrganization($execution);

        $answers = $request->input('answers', []);
        $this->executionService->saveProgress($execution, $answers, auth()->user());

        return redirect()->route('process-executions.show', $execution)
            ->with('success', 'Progress saved successfully.');
    }

    public function confirm(ConfirmProcessExecutionRequest $request, ProcessExecution $execution): RedirectResponse
    {
        $this->ensureSameOrganization($execution);

        $answers = $request->input('answers', []);
        $result = $this->executionService->confirmExecution($execution, $answers, auth()->user());

        if (! $result['success']) {
            return redirect()->route('process-executions.show', $execution)
                ->with('error', $result['message']);
        }

        return redirect()->route('process-executions.show', $execution)
            ->with('success', $result['message']);
    }

    private function ensureSameOrganization(ProcessExecution $execution): void
    {
        if (auth()->check() && auth()->user()->organization_id) {
            $execOrgId = $execution->organization_id ?? $execution->process?->organization_id;
            if ($execOrgId && auth()->user()->organization_id !== $execOrgId) {
                abort(403, 'Unauthorized action for this organization.');
            }
        }
    }
}
