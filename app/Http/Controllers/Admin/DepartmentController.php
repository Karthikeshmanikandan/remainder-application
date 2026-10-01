<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function __construct(
        private AuditLogService $auditLogService
    ) {}

    public function index(Request $request): View
    {
        $this->ensureAdmin();

        $orgId = auth()->user()->organization_id ?? 1;
        $query = Department::where('organization_id', $orgId)->withCount(['processes', 'processTemplates']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $isActive = $request->status === 'active';
            $query->where('is_active', $isActive);
        }

        $departments = $query->orderBy('name')->paginate(15)->withQueryString();

        return view('admin.departments.index', compact('departments'));
    }

    public function create(): View
    {
        $this->ensureAdmin();

        return view('admin.departments.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureAdmin();

        $orgId = auth()->user()->organization_id ?? 1;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', Rule::unique('departments')->where('organization_id', $orgId)],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['organization_id'] = $orgId;
        $validated['is_active'] = $request->boolean('is_active', true);

        $department = DB::transaction(function () use ($validated) {
            $dept = Department::create($validated);

            $this->auditLogService->log(
                action: AuditAction::CREATED,
                auditable: $dept,
                beforeValues: null,
                afterValues: $dept->toArray(),
                summary: "Department '{$dept->name}' ({$dept->code}) created."
            );

            return $dept;
        });

        return redirect()->route('admin.departments.show', $department)
            ->with('success', "Department '{$department->name}' created successfully.");
    }

    public function show(Department $department): View
    {
        $this->ensureAdmin();
        $this->ensureSameOrganization($department);

        $department->loadCount(['processes', 'processTemplates']);
        $department->load([
            'processes' => fn ($q) => $q->with(['responsibleUser', 'responsibleTelegramEmployee'])->latest()->take(10),
            'processTemplates' => fn ($q) => $q->withCount('items')->latest()->take(10),
        ]);

        $auditLogs = $this->auditLogService->getLogsFor($department, 10);

        return view('admin.departments.show', compact('department', 'auditLogs'));
    }

    public function edit(Department $department): View
    {
        $this->ensureAdmin();
        $this->ensureSameOrganization($department);

        return view('admin.departments.edit', compact('department'));
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        $this->ensureAdmin();
        $this->ensureSameOrganization($department);

        $orgId = auth()->user()->organization_id ?? $department->organization_id;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', Rule::unique('departments')->where('organization_id', $orgId)->ignore($department->id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        DB::transaction(function () use ($department, $validated) {
            $original = [
                'name' => $department->name,
                'code' => $department->code,
                'description' => $department->description,
                'is_active' => $department->is_active,
            ];

            $department->update($validated);

            $diffBefore = [];
            $diffAfter = [];
            foreach ($validated as $k => $v) {
                if ($original[$k] != $v) {
                    $diffBefore[$k] = $original[$k];
                    $diffAfter[$k] = $v;
                }
            }

            if (! empty($diffBefore)) {
                $this->auditLogService->log(
                    action: AuditAction::UPDATED,
                    auditable: $department,
                    beforeValues: $diffBefore,
                    afterValues: $diffAfter,
                    summary: "Department '{$department->name}' configuration updated."
                );

                if (isset($diffBefore['is_active'])) {
                    $action = $department->is_active ? AuditAction::ACTIVATED : AuditAction::DEACTIVATED;
                    $this->auditLogService->log(
                        action: $action,
                        auditable: $department,
                        beforeValues: ['is_active' => $diffBefore['is_active']],
                        afterValues: ['is_active' => $department->is_active],
                        summary: "Department '{$department->name}' ".($department->is_active ? 'activated.' : 'deactivated.')
                    );
                }
            }
        });

        return redirect()->route('admin.departments.show', $department)
            ->with('success', "Department '{$department->name}' updated successfully.");
    }

    public function toggleActive(Department $department): RedirectResponse
    {
        $this->ensureAdmin();
        $this->ensureSameOrganization($department);

        DB::transaction(function () use ($department) {
            $newStatus = ! $department->is_active;
            $department->update(['is_active' => $newStatus]);

            $action = $newStatus ? AuditAction::ACTIVATED : AuditAction::DEACTIVATED;
            $this->auditLogService->log(
                action: $action,
                auditable: $department,
                beforeValues: ['is_active' => ! $newStatus],
                afterValues: ['is_active' => $newStatus],
                summary: "Department '{$department->name}' ".($newStatus ? 'activated.' : 'deactivated.')
            );
        });

        $statusMsg = $department->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "Department '{$department->name}' {$statusMsg}.");
    }

    public function destroy(Department $department): RedirectResponse
    {
        $this->ensureAdmin();
        $this->ensureSameOrganization($department);

        if ($department->processes()->exists() || $department->processTemplates()->exists()) {
            return back()->with('error', "Cannot delete department '{$department->name}' because historical processes or templates depend on it. Please deactivate the department instead.");
        }

        DB::transaction(function () use ($department) {
            $name = $department->name;
            $this->auditLogService->log(
                action: AuditAction::DELETED,
                auditable: $department,
                beforeValues: $department->toArray(),
                afterValues: null,
                summary: "Department '{$name}' deleted."
            );

            $department->delete();
        });

        return redirect()->route('admin.departments.index')
            ->with('success', "Department '{$department->name}' deleted.");
    }

    private function ensureAdmin(): void
    {
        if (! auth()->check() || ! auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }
    }

    private function ensureSameOrganization(Department $department): void
    {
        if (auth()->check() && auth()->user()->organization_id && $department->organization_id) {
            if (auth()->user()->organization_id !== $department->organization_id) {
                abort(403, 'Unauthorized action for this organization.');
            }
        }
    }
}
