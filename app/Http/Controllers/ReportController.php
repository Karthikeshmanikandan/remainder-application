<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Process;
use App\Models\User;
use App\Services\ProcessReportingService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __construct(private ProcessReportingService $reportingService) {}

    private function ensureManagerOrAdmin(): void
    {
        if (auth()->user()->isEmployee()) {
            abort(403, 'Unauthorized access to management reports.');
        }
    }

    public function index(Request $request): View
    {
        $this->ensureManagerOrAdmin();

        $dateInfo = $this->reportingService->resolvePeriod(
            $request->date_range,
            $request->start_date,
            $request->end_date
        );

        $data = $this->reportingService->getOverviewReport($dateInfo, auth()->user());

        return view('reports.index', $data);
    }

    public function departments(Request $request): View
    {
        $this->ensureManagerOrAdmin();

        $dateInfo = $this->reportingService->resolvePeriod(
            $request->date_range,
            $request->start_date,
            $request->end_date
        );

        $sortBy = $request->get('sort_by', 'name');
        $direction = $request->get('direction', 'asc');

        $report = $this->reportingService->getDepartmentReports(
            $dateInfo['start'],
            $dateInfo['end'],
            $sortBy,
            $direction
        );

        return view('reports.departments.index', compact('report', 'dateInfo', 'sortBy', 'direction'));
    }

    public function departmentDetail(Request $request, Department $department): View
    {
        $this->ensureManagerOrAdmin();
        $this->ensureSameOrganization($department);

        $dateInfo = $this->reportingService->resolvePeriod(
            $request->date_range,
            $request->start_date,
            $request->end_date
        );

        $data = $this->reportingService->getDepartmentDetailReport(
            $department,
            $dateInfo['start'],
            $dateInfo['end']
        );

        return view('reports.departments.show', array_merge($data, ['dateInfo' => $dateInfo]));
    }

    public function processes(Request $request): View
    {
        $this->ensureManagerOrAdmin();

        $orgId = auth()->user()->organization_id ?? 1;

        $dateInfo = $this->reportingService->resolvePeriod(
            $request->date_range,
            $request->start_date,
            $request->end_date
        );

        $filters = $request->only(['department_id', 'responsible_user_id', 'frequency', 'status', 'search']);

        $processes = $this->reportingService->getProcessReports(
            $dateInfo['start'],
            $dateInfo['end'],
            $filters,
            15
        );

        $departments = Department::where('organization_id', $orgId)->orderBy('name')->get();
        $users = User::where('organization_id', $orgId)->orderBy('name')->get();

        return view('reports.processes.index', compact('processes', 'dateInfo', 'departments', 'users'));
    }

    public function processDetail(Request $request, Process $process): View
    {
        $this->ensureManagerOrAdmin();
        $this->ensureSameOrganization($process);

        $dateInfo = $this->reportingService->resolvePeriod(
            $request->date_range,
            $request->start_date,
            $request->end_date
        );

        $data = $this->reportingService->getProcessDetailReport(
            $process,
            $dateInfo['start'],
            $dateInfo['end'],
            $dateInfo['preset']
        );

        return view('reports.processes.show', array_merge($data, ['dateInfo' => $dateInfo]));
    }

    public function escalations(Request $request): View
    {
        $this->ensureManagerOrAdmin();

        $orgId = auth()->user()->organization_id ?? 1;

        $dateInfo = $this->reportingService->resolvePeriod(
            $request->date_range,
            $request->start_date,
            $request->end_date
        );

        $filters = $request->only(['department_id', 'process_id', 'responsible_user_id', 'recipient_id', 'level', 'status']);

        $report = $this->reportingService->getEscalationReports(
            $dateInfo['start'],
            $dateInfo['end'],
            $filters,
            15
        );

        $departments = Department::where('organization_id', $orgId)->orderBy('name')->get();
        $processes = Process::where('organization_id', $orgId)->orderBy('name')->get();
        $users = User::where('organization_id', $orgId)->orderBy('name')->get();
        $recipients = User::where('organization_id', $orgId)->whereIn('role', [UserRole::ADMIN, UserRole::MANAGER])->orderBy('name')->get();

        return view('reports.escalations.index', compact(
            'report',
            'dateInfo',
            'departments',
            'processes',
            'users',
            'recipients'
        ));
    }

    private function ensureSameOrganization(Department|Process $model): void
    {
        if (auth()->check() && auth()->user()->organization_id && $model->organization_id) {
            if (auth()->user()->organization_id !== $model->organization_id) {
                abort(403, 'Unauthorized action for this organization.');
            }
        }
    }
}
