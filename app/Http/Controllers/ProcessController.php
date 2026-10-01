<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\StoreProcessRequest;
use App\Http\Requests\UpdateProcessRequest;
use App\Models\Department;
use App\Models\Process;
use App\Models\TelegramEmployee;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\ProcessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProcessController extends Controller
{
    public function __construct(private ProcessService $processService) {}

    public function index(Request $request): View
    {
        $user = auth()->user();
        $orgId = $user->organization_id ?? 1;

        $query = Process::with(['department', 'responsibleUser', 'responsibleTelegramEmployee', 'processTemplate'])
            ->where('organization_id', $orgId);

        // Role-based visibility
        if ($user->isEmployee()) {
            $query->where('responsible_user_id', $user->id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $processes = $query->latest()->paginate(10);
        $departments = Department::where('organization_id', $orgId)->where('is_active', true)->orderBy('name')->get();

        return view('processes.index', compact('processes', 'departments'));
    }

    public function create(Request $request): View
    {
        $user = auth()->user();
        if ($user->isEmployee()) {
            abort(403, 'Unauthorized action.');
        }

        $orgId = $user->organization_id ?? 1;

        $departments = Department::where('organization_id', $orgId)->where('is_active', true)
            ->with(['processTemplates' => function ($q) use ($orgId) {
                $q->where('organization_id', $orgId)->where('is_active', true)->with(['items' => function ($qi) {
                    $qi->where('is_active', true)->orderBy('sort_order');
                }]);
            }])
            ->orderBy('name')
            ->get();

        $users = User::where('organization_id', $orgId)->orderBy('name')->get();
        $telegramEmployees = TelegramEmployee::where('organization_id', $orgId)->where('is_active', true)->orderBy('name')->get();
        $managers = User::where('organization_id', $orgId)->whereIn('role', [UserRole::ADMIN, UserRole::MANAGER])->orderBy('name')->get();
        $selectedTemplateId = $request->get('template_id');

        return view('processes.create', compact('departments', 'users', 'telegramEmployees', 'managers', 'selectedTemplateId'));
    }

    public function store(StoreProcessRequest $request): RedirectResponse
    {
        $orgId = auth()->user()->organization_id ?? 1;
        $data = array_merge($request->validated(), ['organization_id' => $orgId]);
        $itemsData = $request->input('items', []);
        $escalationRules = $request->input('escalation_rules', []);

        $process = $this->processService->createProcess($data, $itemsData, $escalationRules);

        return redirect()->route('processes.show', $process)
            ->with('success', "Process '{$process->name}' created successfully.");
    }

    public function show(Process $process): View
    {
        $this->ensureSameOrganization($process);

        $user = auth()->user();
        if ($user->isEmployee() && $process->responsible_user_id !== $user->id) {
            abort(403, 'Unauthorized access to this process.');
        }

        $process->load([
            'department',
            'processTemplate',
            'responsibleUser',
            'responsibleTelegramEmployee',
            'escalationRules.escalateToUser',
            'items' => function ($q) {
                $q->orderBy('sort_order');
            },
            'executions' => function ($q) {
                $q->with(['completedBy', 'completedByTelegramEmployee', 'escalationEvents.acknowledgedBy', 'escalationEvents.rule.escalateToUser'])
                    ->latest('scheduled_for')
                    ->take(10);
            },
        ]);

        $auditLogs = app(AuditLogService::class)->getLogsFor($process, 10);

        return view('processes.show', compact('process', 'auditLogs'));
    }

    public function edit(Process $process): View
    {
        $this->ensureSameOrganization($process);

        $user = auth()->user();
        if ($user->isEmployee()) {
            abort(403, 'Unauthorized action.');
        }

        $orgId = $user->organization_id ?? 1;

        $process->load([
            'department',
            'items' => fn ($q) => $q->orderBy('sort_order'),
            'responsibleUser',
            'responsibleTelegramEmployee',
            'escalationRules.escalateToUser',
        ]);
        $departments = Department::where('organization_id', $orgId)->where('is_active', true)->orderBy('name')->get();
        $users = User::where('organization_id', $orgId)->orderBy('name')->get();
        $telegramEmployees = TelegramEmployee::where('organization_id', $orgId)->where('is_active', true)->orderBy('name')->get();
        $managers = User::where('organization_id', $orgId)->whereIn('role', [UserRole::ADMIN, UserRole::MANAGER])->orderBy('name')->get();

        return view('processes.edit', compact('process', 'departments', 'users', 'telegramEmployees', 'managers'));
    }

    public function update(UpdateProcessRequest $request, Process $process): RedirectResponse
    {
        $this->ensureSameOrganization($process);

        $data = $request->validated();
        $itemsData = $request->input('items', []);
        $escalationRules = $request->input('escalation_rules', []);

        $this->processService->updateProcess($process, $data, $itemsData, $escalationRules);

        return redirect()->route('processes.show', $process)
            ->with('success', "Process '{$process->name}' updated successfully.");
    }

    public function pause(Process $process): RedirectResponse
    {
        $this->ensureSameOrganization($process);

        if (auth()->user()->isEmployee()) {
            abort(403);
        }

        $this->processService->pauseProcess($process);

        return back()->with('success', "Process '{$process->name}' paused.");
    }

    public function resume(Process $process): RedirectResponse
    {
        $this->ensureSameOrganization($process);

        if (auth()->user()->isEmployee()) {
            abort(403);
        }

        $this->processService->resumeProcess($process);

        return back()->with('success', "Process '{$process->name}' resumed.");
    }

    public function cancel(Process $process): RedirectResponse
    {
        $this->ensureSameOrganization($process);

        if (auth()->user()->isEmployee()) {
            abort(403);
        }

        $this->processService->cancelProcess($process);

        return back()->with('success', "Process '{$process->name}' cancelled.");
    }

    private function ensureSameOrganization(Process $process): void
    {
        if (auth()->check() && auth()->user()->organization_id && $process->organization_id) {
            if (auth()->user()->organization_id !== $process->organization_id) {
                abort(403, 'Unauthorized action for this organization.');
            }
        }
    }
}
