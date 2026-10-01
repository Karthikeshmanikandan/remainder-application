<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Process;
use App\Models\ProcessEscalationEvent;
use App\Models\User;
use App\Services\ProcessAccountabilityService;
use App\Services\ProcessEscalationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProcessEscalationController extends Controller
{
    public function __construct(
        private ProcessEscalationService $escalationService,
        private ProcessAccountabilityService $accountabilityService
    ) {}

    private function ensureManagerOrAdmin(): void
    {
        if (auth()->user()->isEmployee()) {
            abort(403, 'Unauthorized access to escalation management.');
        }
    }

    public function index(Request $request): View
    {
        $this->ensureManagerOrAdmin();

        $orgId = auth()->user()->organization_id ?? 1;

        $query = ProcessEscalationEvent::with([
            'execution.process.department',
            'execution.process.responsibleUser',
            'execution.process.responsibleTelegramEmployee',
            'rule.escalateToUser',
            'acknowledgedBy',
        ])->whereHas('execution.process', fn ($q) => $q->where('organization_id', $orgId));

        // Filters
        if ($request->filled('date_range') && $request->date_range !== 'all') {
            $dateInfo = $this->accountabilityService->resolveDateRange(
                $request->date_range,
                $request->start_date,
                $request->end_date
            );
            $query->whereBetween('triggered_at', [$dateInfo['start'], $dateInfo['end']]);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('level') && $request->level !== 'all') {
            $query->where('level', $request->integer('level'));
        }

        if ($request->filled('department_id')) {
            $query->whereHas('execution.process', function ($q) use ($request) {
                $q->where('department_id', $request->integer('department_id'));
            });
        }

        if ($request->filled('process_id')) {
            $query->whereHas('execution', function ($q) use ($request) {
                $q->where('process_id', $request->integer('process_id'));
            });
        }

        if ($request->filled('responsible_user_id')) {
            $query->whereHas('execution.process', function ($q) use ($request) {
                $q->where('responsible_user_id', $request->integer('responsible_user_id'));
            });
        }

        if ($request->filled('recipient_id')) {
            $query->whereHas('rule', function ($q) use ($request) {
                $q->where('escalate_to_user_id', $request->integer('recipient_id'));
            });
        }

        $events = $query->latest('triggered_at')->paginate(15)->withQueryString();

        $departments = Department::where('organization_id', $orgId)->orderBy('name')->get();
        $processes = Process::where('organization_id', $orgId)->orderBy('name')->get();
        $responsibleUsers = User::where('organization_id', $orgId)->whereIn('id', Process::where('organization_id', $orgId)->pluck('responsible_user_id'))->orderBy('name')->get();
        $recipientUsers = User::where('organization_id', $orgId)->whereIn('role', [UserRole::ADMIN, UserRole::MANAGER])->orderBy('name')->get();

        return view('accountability.escalations.index', compact(
            'events',
            'departments',
            'processes',
            'responsibleUsers',
            'recipientUsers'
        ));
    }

    public function acknowledge(Request $request, ProcessEscalationEvent $event): RedirectResponse
    {
        $user = auth()->user();

        if ($user->isEmployee()) {
            abort(403, 'Employees cannot acknowledge process escalations.');
        }

        $eventOrgId = $event->execution?->process?->organization_id;
        if ($user->organization_id && $eventOrgId && $user->organization_id !== $eventOrgId) {
            abort(403, 'Unauthorized action for this organization.');
        }

        $acknowledged = $this->escalationService->acknowledgeEscalation($event, $user);

        if (! $acknowledged) {
            return back()->with('error', 'Escalation could not be acknowledged or was already processed.');
        }

        return back()->with('success', "Level {$event->level} escalation acknowledged successfully.");
    }
}
