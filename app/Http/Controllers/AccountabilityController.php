<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Process;
use App\Models\User;
use App\Services\ProcessAccountabilityService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountabilityController extends Controller
{
    public function __construct(private ProcessAccountabilityService $accountabilityService) {}

    private function ensureManagerOrAdmin(): void
    {
        if (auth()->user()->isEmployee()) {
            abort(403, 'Unauthorized access to management accountability reports.');
        }
    }

    public function departments(Request $request): View
    {
        $this->ensureManagerOrAdmin();

        $data = $this->accountabilityService->getDepartmentStatistics(
            $request->input('date_range', 'this_month'),
            $request->input('start_date'),
            $request->input('end_date')
        );

        return view('accountability.departments.index', $data);
    }

    public function departmentDetail(Request $request, Department $department): View
    {
        $this->ensureManagerOrAdmin();

        $data = $this->accountabilityService->getDepartmentDetail(
            $department,
            $request->input('date_range', 'this_month'),
            $request->input('start_date'),
            $request->input('end_date')
        );

        return view('accountability.departments.show', $data);
    }

    public function users(Request $request): View
    {
        $this->ensureManagerOrAdmin();

        $data = $this->accountabilityService->getUserStatistics(
            $request->input('date_range', 'this_month'),
            $request->input('start_date'),
            $request->input('end_date')
        );

        return view('accountability.users.index', $data);
    }

    public function userDetail(Request $request, User $user): View
    {
        $this->ensureManagerOrAdmin();

        $data = $this->accountabilityService->getUserDetail(
            $user,
            $request->input('date_range', 'this_month'),
            $request->input('start_date'),
            $request->input('end_date')
        );

        return view('accountability.users.show', $data);
    }

    public function processes(Request $request): View
    {
        $this->ensureManagerOrAdmin();

        $departmentId = $request->filled('department_id') ? $request->integer('department_id') : null;
        $userId = $request->filled('responsible_user_id') ? $request->integer('responsible_user_id') : null;

        $data = $this->accountabilityService->getProcessStatistics(
            $request->input('date_range', 'this_month'),
            $request->input('start_date'),
            $request->input('end_date'),
            $departmentId,
            $userId
        );

        $departments = Department::orderBy('name')->get();
        $users = User::whereIn('id', Process::pluck('responsible_user_id'))->orderBy('name')->get();

        return view('accountability.processes.index', array_merge($data, [
            'departments' => $departments,
            'users' => $users,
        ]));
    }

    public function processDetail(Request $request, Process $process): View
    {
        $this->ensureManagerOrAdmin();

        $data = $this->accountabilityService->getProcessDetail(
            $process,
            $request->input('date_range', 'this_month'),
            $request->input('start_date'),
            $request->input('end_date')
        );

        return view('accountability.processes.show', $data);
    }
}
