<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\TelegramEmployee;
use App\Services\TelegramEmployeeService;
use App\Services\TelegramService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TelegramEmployeeController extends Controller
{
    public function __construct(
        private TelegramEmployeeService $employeeService,
        private TelegramService $telegramService
    ) {}

    public function index(Request $request): View
    {
        $this->ensureAdminOrManager();

        $orgId = auth()->user()->organization_id ?? 1;
        $query = TelegramEmployee::with(['department', 'telegramAccount'])
            ->where('organization_id', $orgId)
            ->withCount(['assignedTasks', 'assignedProcesses']);

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $employees = $query->orderBy('name')->paginate(15)->withQueryString();
        $departments = Department::where('organization_id', $orgId)->where('is_active', true)->orderBy('name')->get();

        return view('admin.telegram-employees.index', compact('employees', 'departments'));
    }

    public function create(): View
    {
        $this->ensureAdminOrManager();

        $orgId = auth()->user()->organization_id ?? 1;
        $departments = Department::where('organization_id', $orgId)->where('is_active', true)->orderBy('name')->get();
        $suggestedCode = $this->employeeService->generateUniqueEmployeeCode($orgId);

        return view('admin.telegram-employees.create', compact('departments', 'suggestedCode'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureAdminOrManager();

        $orgId = auth()->user()->organization_id ?? 1;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'employee_code' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['organization_id'] = $orgId;

        $employee = $this->employeeService->createEmployee($validated);

        return redirect()->route('admin.telegram-employees.show', $employee)
            ->with('success', "Telegram-only Employee '{$employee->name}' created successfully.");
    }

    public function show(TelegramEmployee $telegramEmployee): View
    {
        $this->ensureAdminOrManager();
        $this->ensureSameOrganization($telegramEmployee);

        $telegramEmployee->load(['department', 'telegramAccount', 'assignedTasks', 'assignedProcesses']);

        $telegramAccount = $telegramEmployee->telegramAccount;
        $botUsername = $this->telegramService->getBotUsername();
        $deepLink = ($telegramAccount && $telegramAccount->hasValidVerificationCode())
            ? $this->telegramService->getDeepLink($telegramAccount->verification_code)
            : null;

        return view('admin.telegram-employees.show', compact('telegramEmployee', 'telegramAccount', 'botUsername', 'deepLink'));
    }

    public function edit(TelegramEmployee $telegramEmployee): View
    {
        $this->ensureAdminOrManager();
        $this->ensureSameOrganization($telegramEmployee);

        $orgId = auth()->user()->organization_id ?? 1;
        $departments = Department::where('organization_id', $orgId)->where('is_active', true)->orderBy('name')->get();

        return view('admin.telegram-employees.edit', compact('telegramEmployee', 'departments'));
    }

    public function update(Request $request, TelegramEmployee $telegramEmployee): RedirectResponse
    {
        $this->ensureAdminOrManager();
        $this->ensureSameOrganization($telegramEmployee);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $this->employeeService->updateEmployee($telegramEmployee, $validated);

        return redirect()->route('admin.telegram-employees.show', $telegramEmployee)
            ->with('success', "Telegram-only Employee '{$telegramEmployee->name}' updated successfully.");
    }

    public function toggleActive(TelegramEmployee $telegramEmployee): RedirectResponse
    {
        $this->ensureAdminOrManager();
        $this->ensureSameOrganization($telegramEmployee);

        $this->employeeService->toggleActive($telegramEmployee);
        $statusText = $telegramEmployee->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "Employee '{$telegramEmployee->name}' has been {$statusText}.");
    }

    public function generateCode(TelegramEmployee $telegramEmployee): RedirectResponse
    {
        $this->ensureAdminOrManager();
        $this->ensureSameOrganization($telegramEmployee);

        $code = $this->employeeService->generateVerificationCode($telegramEmployee);

        return back()->with('linking_code', $code)
            ->with('success', "One-time linking code generated: {$code} (Valid for 15 minutes).");
    }

    public function unlink(TelegramEmployee $telegramEmployee): RedirectResponse
    {
        $this->ensureAdminOrManager();
        $this->ensureSameOrganization($telegramEmployee);

        $this->employeeService->unlinkAccount($telegramEmployee);

        return back()->with('success', "Telegram account unlinked for '{$telegramEmployee->name}'.");
    }

    private function ensureAdminOrManager(): void
    {
        if (! auth()->check() || (! auth()->user()->isAdmin() && ! auth()->user()->isManager())) {
            abort(403, 'Unauthorized action.');
        }
    }

    private function ensureSameOrganization(TelegramEmployee $employee): void
    {
        if (auth()->check() && auth()->user()->organization_id && $employee->organization_id) {
            if (auth()->user()->organization_id !== $employee->organization_id) {
                abort(403, 'Unauthorized action for this organization.');
            }
        }
    }
}
