<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Enums\ProcessFrequency;
use App\Enums\ProcessResponseType;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\ProcessTemplate;
use App\Models\ProcessTemplateItem;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProcessTemplateController extends Controller
{
    public function __construct(
        private AuditLogService $auditLogService
    ) {}

    public function index(Request $request): View
    {
        $this->ensureAdminOrManager();

        $orgId = auth()->user()->organization_id ?? 1;
        $query = ProcessTemplate::where('organization_id', $orgId)->with(['department'])->withCount(['items', 'processes']);

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        if ($request->filled('status')) {
            $isActive = $request->status === 'active';
            $query->where('is_active', $isActive);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $templates = $query->orderBy('name')->paginate(15)->withQueryString();
        $departments = Department::where('organization_id', $orgId)->where('is_active', true)->orderBy('name')->get();

        return view('admin.process-templates.index', compact('templates', 'departments'));
    }

    public function create(): View
    {
        $this->ensureAdminOrManager();

        $orgId = auth()->user()->organization_id ?? 1;
        $departments = Department::where('organization_id', $orgId)->where('is_active', true)->orderBy('name')->get();
        $frequencies = ProcessFrequency::cases();
        $responseTypes = ProcessResponseType::cases();

        return view('admin.process-templates.create', compact('departments', 'frequencies', 'responseTypes'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureAdminOrManager();

        $orgId = auth()->user()->organization_id ?? 1;

        $validated = $request->validate([
            'department_id' => ['required', 'exists:departments,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', Rule::unique('process_templates')->where('organization_id', $orgId)],
            'description' => ['nullable', 'string', 'max:1000'],
            'frequency_default' => ['required', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'items' => ['nullable', 'array'],
            'items.*.question' => ['required', 'string', 'max:500'],
            'items.*.description' => ['nullable', 'string', 'max:1000'],
            'items.*.response_type' => ['required', 'string'],
            'items.*.is_required' => ['nullable', 'boolean'],
        ]);

        $template = DB::transaction(function () use ($request, $validated, $orgId) {
            $template = ProcessTemplate::create([
                'organization_id' => $orgId,
                'department_id' => $validated['department_id'],
                'name' => $validated['name'],
                'code' => $validated['code'],
                'description' => $validated['description'] ?? null,
                'frequency_default' => ProcessFrequency::from($validated['frequency_default']),
                'is_active' => $request->boolean('is_active', true),
                'is_system_template' => false,
            ]);

            $itemsData = $request->input('items', []);
            $createdItems = [];
            foreach ($itemsData as $index => $itemData) {
                if (empty($itemData['question'])) {
                    continue;
                }
                $item = ProcessTemplateItem::create([
                    'process_template_id' => $template->id,
                    'question' => trim($itemData['question']),
                    'description' => $itemData['description'] ?? null,
                    'response_type' => ProcessResponseType::from($itemData['response_type']),
                    'sort_order' => $index + 1,
                    'is_required' => ! empty($itemData['is_required']),
                    'is_active' => true,
                ]);
                $createdItems[] = $item;
            }

            $this->auditLogService->log(
                action: AuditAction::CREATED,
                auditable: $template,
                beforeValues: null,
                afterValues: $template->toArray(),
                summary: "Process template '{$template->name}' ({$template->code}) created with ".count($createdItems).' questions.'
            );

            return $template;
        });

        return redirect()->route('admin.process-templates.show', $template)
            ->with('success', "Process template '{$template->name}' created successfully.");
    }

    public function show(ProcessTemplate $processTemplate): View
    {
        $this->ensureAdminOrManager();
        $this->ensureSameOrganization($processTemplate);

        $processTemplate->load(['department', 'items' => fn ($q) => $q->orderBy('sort_order')]);
        $processTemplate->loadCount('processes');

        $activeProcesses = $processTemplate->processes()
            ->with(['department', 'responsibleUser'])
            ->latest()
            ->take(10)
            ->get();

        $auditLogs = $this->auditLogService->getLogsFor($processTemplate, 10);

        return view('admin.process-templates.show', compact('processTemplate', 'activeProcesses', 'auditLogs'));
    }

    public function edit(ProcessTemplate $processTemplate): View
    {
        $this->ensureAdminOrManager();
        $this->ensureSameOrganization($processTemplate);

        $orgId = auth()->user()->organization_id ?? $processTemplate->organization_id;
        $processTemplate->load(['department', 'items' => fn ($q) => $q->orderBy('sort_order')]);
        $departments = Department::where('organization_id', $orgId)->where('is_active', true)->orderBy('name')->get();
        $frequencies = ProcessFrequency::cases();
        $responseTypes = ProcessResponseType::cases();

        return view('admin.process-templates.edit', compact('processTemplate', 'departments', 'frequencies', 'responseTypes'));
    }

    public function update(Request $request, ProcessTemplate $processTemplate): RedirectResponse
    {
        $this->ensureAdminOrManager();
        $this->ensureSameOrganization($processTemplate);

        $orgId = auth()->user()->organization_id ?? $processTemplate->organization_id;

        $validated = $request->validate([
            'department_id' => ['required', 'exists:departments,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', Rule::unique('process_templates')->where('organization_id', $orgId)->ignore($processTemplate->id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'frequency_default' => ['required', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'items' => ['nullable', 'array'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.question' => ['required', 'string', 'max:500'],
            'items.*.description' => ['nullable', 'string', 'max:1000'],
            'items.*.response_type' => ['required', 'string'],
            'items.*.is_required' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($processTemplate, $request, $validated) {
            $original = $processTemplate->toArray();

            $processTemplate->update([
                'department_id' => $validated['department_id'],
                'name' => $validated['name'],
                'code' => $validated['code'],
                'description' => $validated['description'] ?? null,
                'frequency_default' => ProcessFrequency::from($validated['frequency_default']),
                'is_active' => $request->boolean('is_active', true),
            ]);

            $this->auditLogService->log(
                action: AuditAction::UPDATED,
                auditable: $processTemplate,
                beforeValues: $original,
                afterValues: $processTemplate->fresh()->toArray(),
                summary: "Process template '{$processTemplate->name}' updated."
            );

            // Sync Template Items
            $itemsData = $request->input('items', []);
            $keptItemIds = [];
            $sortOrder = 1;

            foreach ($itemsData as $itemData) {
                if (empty($itemData['question'])) {
                    continue;
                }

                if (! empty($itemData['id'])) {
                    $item = ProcessTemplateItem::where('process_template_id', $processTemplate->id)
                        ->where('id', $itemData['id'])
                        ->first();

                    if ($item) {
                        $item->update([
                            'question' => trim($itemData['question']),
                            'description' => $itemData['description'] ?? null,
                            'response_type' => ProcessResponseType::from($itemData['response_type']),
                            'sort_order' => $sortOrder++,
                            'is_required' => ! empty($itemData['is_required']),
                        ]);
                        $keptItemIds[] = $item->id;
                    }
                } else {
                    $newItem = ProcessTemplateItem::create([
                        'process_template_id' => $processTemplate->id,
                        'question' => trim($itemData['question']),
                        'description' => $itemData['description'] ?? null,
                        'response_type' => ProcessResponseType::from($itemData['response_type']),
                        'sort_order' => $sortOrder++,
                        'is_required' => ! empty($itemData['is_required']),
                        'is_active' => true,
                    ]);
                    $keptItemIds[] = $newItem->id;
                }
            }

            // Delete removed template items
            ProcessTemplateItem::where('process_template_id', $processTemplate->id)
                ->whereNotIn('id', $keptItemIds)
                ->delete();

            $this->auditLogService->log(
                action: AuditAction::CONFIGURED,
                auditable: $processTemplate,
                beforeValues: null,
                afterValues: ['active_questions_count' => count($keptItemIds)],
                summary: "Process template '{$processTemplate->name}' checklist questions updated."
            );
        });

        return redirect()->route('admin.process-templates.show', $processTemplate)
            ->with('success', "Process template '{$processTemplate->name}' updated successfully.");
    }

    public function toggleActive(ProcessTemplate $processTemplate): RedirectResponse
    {
        $this->ensureAdminOrManager();
        $this->ensureSameOrganization($processTemplate);

        DB::transaction(function () use ($processTemplate) {
            $newStatus = ! $processTemplate->is_active;
            $processTemplate->update(['is_active' => $newStatus]);

            $action = $newStatus ? AuditAction::ACTIVATED : AuditAction::DEACTIVATED;
            $this->auditLogService->log(
                action: $action,
                auditable: $processTemplate,
                beforeValues: ['is_active' => ! $newStatus],
                afterValues: ['is_active' => $newStatus],
                summary: "Process template '{$processTemplate->name}' ".($newStatus ? 'activated.' : 'deactivated.')
            );
        });

        $statusMsg = $processTemplate->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "Process template '{$processTemplate->name}' {$statusMsg}.");
    }

    public function destroy(ProcessTemplate $processTemplate): RedirectResponse
    {
        $this->ensureAdminOrManager();
        $this->ensureSameOrganization($processTemplate);

        if ($processTemplate->processes()->exists()) {
            return back()->with('error', "Cannot delete template '{$processTemplate->name}' because existing processes depend on it. Please deactivate the template instead.");
        }

        DB::transaction(function () use ($processTemplate) {
            $name = $processTemplate->name;
            $this->auditLogService->log(
                action: AuditAction::DELETED,
                auditable: $processTemplate,
                beforeValues: $processTemplate->toArray(),
                afterValues: null,
                summary: "Process template '{$name}' deleted."
            );

            $processTemplate->items()->delete();
            $processTemplate->delete();
        });

        return redirect()->route('admin.process-templates.index')
            ->with('success', "Process template '{$processTemplate->name}' deleted.");
    }

    private function ensureAdminOrManager(): void
    {
        if (! auth()->check() || auth()->user()->isEmployee()) {
            abort(403, 'Unauthorized action.');
        }
    }

    private function ensureSameOrganization(ProcessTemplate $template): void
    {
        if (auth()->check() && auth()->user()->organization_id && $template->organization_id) {
            if (auth()->user()->organization_id !== $template->organization_id) {
                abort(403, 'Unauthorized action for this organization.');
            }
        }
    }
}
