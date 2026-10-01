<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function __construct(
        private AuditLogService $auditLogService
    ) {}

    public function index(Request $request): View
    {
        $this->ensureAdmin();

        $orgId = auth()->user()->organization_id;

        $filters = $request->only([
            'user_id',
            'action',
            'auditable_type',
            'auditable_id',
            'date_from',
            'date_to',
            'search',
        ]);

        if ($orgId) {
            $filters['organization_id'] = $orgId;
        }

        $auditLogs = $this->auditLogService->getFilteredLogs($filters, 20);

        $users = User::when($orgId, fn ($q) => $q->where('organization_id', $orgId))->orderBy('name')->get();
        $actions = AuditAction::cases();

        $entityTypes = [
            'Process' => 'Process',
            'ProcessTemplate' => 'Process Template',
            'Department' => 'Department',
            'ProcessEscalationEvent' => 'Escalation Event',
            'ProcessExecution' => 'Process Execution',
            'Task' => 'Task',
            'TelegramEmployee' => 'Telegram Employee',
            'Organization' => 'Organization',
            'User' => 'User',
        ];

        return view('admin.audit-logs.index', compact('auditLogs', 'users', 'actions', 'entityTypes', 'filters'));
    }

    public function show(AuditLog $auditLog): View
    {
        $this->ensureAdmin();
        $this->ensureSameOrganization($auditLog);

        $auditLog->load(['user', 'actor', 'auditable']);

        return view('admin.audit-logs.show', compact('auditLog'));
    }

    private function ensureAdmin(): void
    {
        if (! auth()->check() || ! auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }
    }

    private function ensureSameOrganization(AuditLog $auditLog): void
    {
        if (auth()->check() && auth()->user()->organization_id && $auditLog->organization_id) {
            if (auth()->user()->organization_id !== $auditLog->organization_id) {
                abort(403, 'Unauthorized action for this organization.');
            }
        }
    }
}
