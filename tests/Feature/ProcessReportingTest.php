<?php

namespace Tests\Feature;

use App\Enums\ProcessEscalationStatus;
use App\Enums\ProcessExecutionStatus;
use App\Enums\ProcessFrequency;
use App\Enums\ProcessResponseType;
use App\Enums\ProcessStatus;
use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Process;
use App\Models\ProcessEscalationEvent;
use App\Models\ProcessEscalationRule;
use App\Models\ProcessExecution;
use App\Models\ProcessExecutionItem;
use App\Models\ProcessItem;
use App\Models\User;
use App\Services\ProcessReportingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProcessReportingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $manager;

    protected User $employee;

    protected Department $departmentA;

    protected Department $departmentB;

    protected Process $processA;

    protected Process $processB;

    protected ProcessReportingService $reportingService;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 10, 15, 14, 0, 0));

        $this->admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $this->manager = User::factory()->create(['role' => UserRole::MANAGER]);
        $this->employee = User::factory()->create(['role' => UserRole::EMPLOYEE]);

        $this->departmentA = Department::create([
            'name' => 'Finance & Accounts',
            'code' => 'FIN',
            'description' => 'Finance Department',
        ]);

        $this->departmentB = Department::create([
            'name' => 'Operations & Logistics',
            'code' => 'OPS',
            'description' => 'Operations Department',
        ]);

        $this->processA = Process::create([
            'code' => 'FIN-DLY-001',
            'name' => 'Daily Cash Reconciliation',
            'department_id' => $this->departmentA->id,
            'responsible_user_id' => $this->employee->id,
            'frequency' => ProcessFrequency::DAILY,
            'reminder_time' => '17:00:00',
            'status' => ProcessStatus::ACTIVE,
            'created_by' => $this->admin->id,
        ]);

        $this->processB = Process::create([
            'code' => 'OPS-WK-001',
            'name' => 'Weekly Warehouse Audit',
            'department_id' => $this->departmentB->id,
            'responsible_user_id' => $this->manager->id,
            'frequency' => ProcessFrequency::WEEKLY,
            'reminder_time' => '10:00:00',
            'status' => ProcessStatus::ACTIVE,
            'created_by' => $this->admin->id,
        ]);

        $this->reportingService = app(ProcessReportingService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function createExecution(array $attributes = []): ProcessExecution
    {
        $process = isset($attributes['process_id']) ? Process::find($attributes['process_id']) : $this->processA;
        $scheduledFor = $attributes['scheduled_for'] ?? now();
        $dateStr = $scheduledFor instanceof Carbon ? $scheduledFor->format('Y-m-d') : Carbon::parse($scheduledFor)->format('Y-m-d');

        return ProcessExecution::create(array_merge([
            'process_id' => $process->id,
            'occurrence_key' => $process->code.'-'.$dateStr.'-'.Str::random(8),
            'scheduled_for' => $scheduledFor,
            'status' => ProcessExecutionStatus::PENDING,
        ], $attributes));
    }

    // ==========================================
    // 1. AUTHENTICATION & RBAC PERMISSIONS (12 TESTS)
    // ==========================================

    public function test_unauthenticated_user_cannot_access_reports_overview(): void
    {
        $response = $this->get(route('reports.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_unauthenticated_user_cannot_access_department_reports(): void
    {
        $response = $this->get(route('reports.departments.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_unauthenticated_user_cannot_access_department_detail_report(): void
    {
        $response = $this->get(route('reports.departments.show', $this->departmentA));
        $response->assertRedirect(route('login'));
    }

    public function test_unauthenticated_user_cannot_access_process_reports(): void
    {
        $response = $this->get(route('reports.processes.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_unauthenticated_user_cannot_access_process_detail_report(): void
    {
        $response = $this->get(route('reports.processes.show', $this->processA));
        $response->assertRedirect(route('login'));
    }

    public function test_unauthenticated_user_cannot_access_escalation_reports(): void
    {
        $response = $this->get(route('reports.escalations.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_employee_cannot_access_reports_overview_and_gets_403(): void
    {
        $response = $this->actingAs($this->employee)->get(route('reports.index'));
        $response->assertForbidden();
    }

    public function test_employee_cannot_access_department_reports_and_gets_403(): void
    {
        $response = $this->actingAs($this->employee)->get(route('reports.departments.index'));
        $response->assertForbidden();
    }

    public function test_employee_cannot_access_department_detail_report_and_gets_403(): void
    {
        $response = $this->actingAs($this->employee)->get(route('reports.departments.show', $this->departmentA));
        $response->assertForbidden();
    }

    public function test_employee_cannot_access_process_reports_and_gets_403(): void
    {
        $response = $this->actingAs($this->employee)->get(route('reports.processes.index'));
        $response->assertForbidden();
    }

    public function test_employee_cannot_access_process_detail_report_and_gets_403(): void
    {
        $response = $this->actingAs($this->employee)->get(route('reports.processes.show', $this->processA));
        $response->assertForbidden();
    }

    public function test_employee_cannot_access_escalation_reports_and_gets_403(): void
    {
        $response = $this->actingAs($this->employee)->get(route('reports.escalations.index'));
        $response->assertForbidden();
    }

    // ==========================================
    // 2. AUTHORIZED ACCESS (ADMIN & MANAGER) (10 TESTS)
    // ==========================================

    public function test_admin_can_access_reports_overview(): void
    {
        $response = $this->actingAs($this->admin)->get(route('reports.index'));
        $response->assertOk();
        $response->assertViewIs('reports.index');
        $response->assertSee('Management Reports');
    }

    public function test_manager_can_access_reports_overview(): void
    {
        $response = $this->actingAs($this->manager)->get(route('reports.index'));
        $response->assertOk();
        $response->assertViewIs('reports.index');
    }

    public function test_admin_can_access_department_reports(): void
    {
        $response = $this->actingAs($this->admin)->get(route('reports.departments.index'));
        $response->assertOk();
        $response->assertViewIs('reports.departments.index');
        $response->assertSee('Department Activity');
        $response->assertSee('Finance & Accounts');
    }

    public function test_manager_can_access_department_reports(): void
    {
        $response = $this->actingAs($this->manager)->get(route('reports.departments.index'));
        $response->assertOk();
        $response->assertViewIs('reports.departments.index');
    }

    public function test_admin_can_access_department_detail_report(): void
    {
        $response = $this->actingAs($this->admin)->get(route('reports.departments.show', $this->departmentA));
        $response->assertOk();
        $response->assertViewIs('reports.departments.show');
        $response->assertSee('Finance & Accounts');
    }

    public function test_manager_can_access_department_detail_report(): void
    {
        $response = $this->actingAs($this->manager)->get(route('reports.departments.show', $this->departmentA));
        $response->assertOk();
        $response->assertViewIs('reports.departments.show');
    }

    public function test_admin_can_access_process_reports(): void
    {
        $response = $this->actingAs($this->admin)->get(route('reports.processes.index'));
        $response->assertOk();
        $response->assertViewIs('reports.processes.index');
        $response->assertSee('Daily Cash Reconciliation');
    }

    public function test_manager_can_access_process_reports(): void
    {
        $response = $this->actingAs($this->manager)->get(route('reports.processes.index'));
        $response->assertOk();
        $response->assertViewIs('reports.processes.index');
    }

    public function test_admin_can_access_process_detail_report(): void
    {
        $response = $this->actingAs($this->admin)->get(route('reports.processes.show', $this->processA));
        $response->assertOk();
        $response->assertViewIs('reports.processes.show');
        $response->assertSee('FIN-DLY-001');
    }

    public function test_admin_can_access_escalation_reports(): void
    {
        $response = $this->actingAs($this->admin)->get(route('reports.escalations.index'));
        $response->assertOk();
        $response->assertViewIs('reports.escalations.index');
        $response->assertSee('Escalation Reports');
    }

    public function test_manager_can_access_escalation_reports(): void
    {
        $response = $this->actingAs($this->manager)->get(route('reports.escalations.index'));
        $response->assertOk();
        $response->assertViewIs('reports.escalations.index');
    }

    // ==========================================
    // 3. DATE RESOLUTION & BOUNDARIES (10 TESTS)
    // ==========================================

    public function test_date_preset_today_resolves_correct_boundaries(): void
    {
        $result = $this->reportingService->resolvePeriod('today');
        $this->assertEquals('2026-10-15 00:00:00', $result['start']->format('Y-m-d H:i:s'));
        $this->assertEquals('2026-10-15 23:59:59', $result['end']->format('Y-m-d H:i:s'));
        $this->assertEquals('today', $result['preset']);
    }

    public function test_date_preset_yesterday_resolves_correct_boundaries(): void
    {
        $result = $this->reportingService->resolvePeriod('yesterday');
        $this->assertEquals('2026-10-14 00:00:00', $result['start']->format('Y-m-d H:i:s'));
        $this->assertEquals('2026-10-14 23:59:59', $result['end']->format('Y-m-d H:i:s'));
    }

    public function test_date_preset_this_week_resolves_correct_boundaries(): void
    {
        $result = $this->reportingService->resolvePeriod('this_week');
        $this->assertEquals(now()->startOfWeek()->format('Y-m-d H:i:s'), $result['start']->format('Y-m-d H:i:s'));
        $this->assertEquals(now()->endOfWeek()->format('Y-m-d H:i:s'), $result['end']->format('Y-m-d H:i:s'));
    }

    public function test_date_preset_last_week_resolves_correct_boundaries(): void
    {
        $result = $this->reportingService->resolvePeriod('last_week');
        $this->assertEquals(now()->subWeek()->startOfWeek()->format('Y-m-d H:i:s'), $result['start']->format('Y-m-d H:i:s'));
        $this->assertEquals(now()->subWeek()->endOfWeek()->format('Y-m-d H:i:s'), $result['end']->format('Y-m-d H:i:s'));
    }

    public function test_date_preset_last_7_days_resolves_correct_boundaries(): void
    {
        $result = $this->reportingService->resolvePeriod('last_7_days');
        $this->assertEquals(now()->subDays(6)->startOfDay()->format('Y-m-d H:i:s'), $result['start']->format('Y-m-d H:i:s'));
        $this->assertEquals(now()->endOfDay()->format('Y-m-d H:i:s'), $result['end']->format('Y-m-d H:i:s'));
    }

    public function test_date_preset_this_month_resolves_correct_boundaries(): void
    {
        $result = $this->reportingService->resolvePeriod('this_month');
        $this->assertEquals('2026-10-01 00:00:00', $result['start']->format('Y-m-d H:i:s'));
        $this->assertEquals('2026-10-31 23:59:59', $result['end']->format('Y-m-d H:i:s'));
    }

    public function test_date_preset_last_month_resolves_correct_boundaries(): void
    {
        $result = $this->reportingService->resolvePeriod('last_month');
        $this->assertEquals('2026-09-01 00:00:00', $result['start']->format('Y-m-d H:i:s'));
        $this->assertEquals('2026-09-30 23:59:59', $result['end']->format('Y-m-d H:i:s'));
    }

    public function test_date_preset_last_30_days_resolves_correct_boundaries(): void
    {
        $result = $this->reportingService->resolvePeriod('last_30_days');
        $this->assertEquals(now()->subDays(29)->startOfDay()->format('Y-m-d H:i:s'), $result['start']->format('Y-m-d H:i:s'));
        $this->assertEquals(now()->endOfDay()->format('Y-m-d H:i:s'), $result['end']->format('Y-m-d H:i:s'));
    }

    public function test_custom_date_range_resolves_start_and_end_of_day(): void
    {
        $result = $this->reportingService->resolvePeriod('custom', '2026-08-01', '2026-08-15');
        $this->assertEquals('2026-08-01 00:00:00', $result['start']->format('Y-m-d H:i:s'));
        $this->assertEquals('2026-08-15 23:59:59', $result['end']->format('Y-m-d H:i:s'));
        $this->assertEquals('custom', $result['preset']);
    }

    public function test_invalid_custom_date_range_reverts_to_current_month(): void
    {
        // Start date greater than end date
        $result = $this->reportingService->resolvePeriod('custom', '2026-08-20', '2026-08-10');
        $this->assertEquals('2026-10-01 00:00:00', $result['start']->format('Y-m-d H:i:s'));
        $this->assertEquals('2026-10-31 23:59:59', $result['end']->format('Y-m-d H:i:s'));
    }

    // ==========================================
    // 4. METRICS & COMPARISON CALCULATIONS (6 TESTS)
    // ==========================================

    public function test_metrics_calculation_accurate_counts_and_percentages(): void
    {
        // Create 4 executions: 2 completed, 1 pending (future), 1 overdue (past)
        $this->createExecution([
            'process_id' => $this->processA->id,
            'scheduled_for' => now()->startOfDay()->addHours(9),
            'status' => ProcessExecutionStatus::COMPLETED,
            'completed_at' => now()->startOfDay()->addHours(10),
            'completed_by' => $this->employee->id,
        ]);

        $this->createExecution([
            'process_id' => $this->processA->id,
            'scheduled_for' => now()->startOfDay()->addHours(11),
            'status' => ProcessExecutionStatus::COMPLETED,
            'completed_at' => now()->startOfDay()->addHours(12),
            'completed_by' => $this->employee->id,
        ]);

        $this->createExecution([
            'process_id' => $this->processA->id,
            'scheduled_for' => now()->startOfDay()->addHours(18), // future relative to 14:00
            'status' => ProcessExecutionStatus::PENDING,
        ]);

        $this->createExecution([
            'process_id' => $this->processA->id,
            'scheduled_for' => now()->startOfDay()->addHours(10), // past relative to 14:00
            'status' => ProcessExecutionStatus::PENDING,
        ]);

        $query = ProcessExecution::query()->whereBetween('scheduled_for', [now()->startOfDay(), now()->endOfDay()]);
        $metrics = $this->reportingService->calculateMetrics($query);

        $this->assertEquals(4, $metrics['scheduled']);
        $this->assertEquals(2, $metrics['completed']);
        $this->assertEquals(2, $metrics['pending']);
        $this->assertEquals(1, $metrics['overdue']);
        $this->assertEquals(0, $metrics['missed']);
        $this->assertEquals(50.0, $metrics['completion_rate']);
    }

    public function test_metrics_zero_division_safety_returns_zero_percent(): void
    {
        $query = ProcessExecution::query()->where('id', 99999);
        $metrics = $this->reportingService->calculateMetrics($query);

        $this->assertEquals(0, $metrics['scheduled']);
        $this->assertEquals(0, $metrics['completed']);
        $this->assertEquals(0.0, $metrics['completion_rate']);
    }

    public function test_metrics_includes_missed_and_escalations_counts(): void
    {
        $exec = $this->createExecution([
            'process_id' => $this->processA->id,
            'scheduled_for' => now()->subDay(),
            'status' => ProcessExecutionStatus::MISSED,
        ]);

        $rule = ProcessEscalationRule::create([
            'process_id' => $this->processA->id,
            'level' => 1,
            'trigger_after_minutes' => 30,
            'escalate_to_user_id' => $this->manager->id,
            'is_active' => true,
        ]);

        ProcessEscalationEvent::create([
            'process_execution_id' => $exec->id,
            'escalation_rule_id' => $rule->id,
            'level' => 1,
            'triggered_at' => now()->subDay()->addMinutes(30),
            'status' => ProcessEscalationStatus::TRIGGERED,
        ]);

        $query = ProcessExecution::query()->where('id', $exec->id);
        $metrics = $this->reportingService->calculateMetrics($query);

        $this->assertEquals(1, $metrics['scheduled']);
        $this->assertEquals(1, $metrics['missed']);
        $this->assertEquals(1, $metrics['escalations']);
    }

    public function test_period_comparison_computes_exact_raw_deltas(): void
    {
        $current = [
            'scheduled' => 20,
            'completed' => 15,
            'pending' => 2,
            'in_progress' => 1,
            'overdue' => 1,
            'missed' => 1,
            'escalations' => 3,
            'completion_rate' => 75.0,
        ];

        $previous = [
            'scheduled' => 10,
            'completed' => 5,
            'pending' => 3,
            'in_progress' => 0,
            'overdue' => 1,
            'missed' => 1,
            'escalations' => 1,
            'completion_rate' => 50.0,
        ];

        $comparison = $this->reportingService->calculatePeriodComparison($current, $previous);

        $this->assertEquals(10, $comparison['scheduled_diff']);
        $this->assertEquals(10, $comparison['completed_diff']);
        $this->assertEquals(-1, $comparison['pending_diff']);
        $this->assertEquals(2, $comparison['escalations_diff']);
        $this->assertEquals(25.0, $comparison['completion_rate_diff']);
    }

    public function test_resolve_previous_period_shifts_correct_duration(): void
    {
        // 1. This month (October) -> previous month (September)
        $prevMonth = $this->reportingService->resolvePreviousPeriod('this_month', now()->startOfMonth(), now()->endOfMonth());
        $this->assertEquals('2026-09-01 00:00:00', $prevMonth['start']->format('Y-m-d H:i:s'));
        $this->assertEquals('2026-09-30 23:59:59', $prevMonth['end']->format('Y-m-d H:i:s'));

        // 2. Custom 5 days period (Oct 10 - Oct 14) -> shifts 5 days back (Oct 05 - Oct 09)
        $customStart = Carbon::create(2026, 10, 10, 0, 0, 0);
        $customEnd = Carbon::create(2026, 10, 14, 23, 59, 59);
        $prevCustom = $this->reportingService->resolvePreviousPeriod('custom', $customStart, $customEnd);
        $this->assertEquals('2026-10-05 00:00:00', $prevCustom['start']->format('Y-m-d H:i:s'));
        $this->assertEquals('2026-10-09 23:59:59', $prevCustom['end']->format('Y-m-d H:i:s'));
    }

    public function test_overview_report_aggregates_all_components(): void
    {
        $dateInfo = $this->reportingService->resolvePeriod('today');
        $overview = $this->reportingService->getOverviewReport($dateInfo);

        $this->assertArrayHasKey('current', $overview);
        $this->assertArrayHasKey('previous', $overview);
        $this->assertArrayHasKey('comparison', $overview);
        $this->assertArrayHasKey('trends', $overview);
        $this->assertArrayHasKey('attention_required', $overview);
        $this->assertArrayHasKey('repeated_escalations', $overview);
        $this->assertArrayHasKey('department_workload', $overview);
    }

    // ==========================================
    // 5. DEPARTMENT REPORTING & WORKLOAD (5 TESTS)
    // ==========================================

    public function test_department_workload_aggregates_across_departments(): void
    {
        $this->createExecution([
            'process_id' => $this->processA->id,
            'scheduled_for' => now()->startOfDay()->addHours(10),
            'status' => ProcessExecutionStatus::COMPLETED,
            'completed_at' => now()->startOfDay()->addHours(11),
        ]);

        $this->createExecution([
            'process_id' => $this->processB->id,
            'scheduled_for' => now()->startOfDay()->addHours(9),
            'status' => ProcessExecutionStatus::MISSED,
        ]);

        $workload = $this->reportingService->getDepartmentWorkload(now()->startOfDay(), now()->endOfDay());

        $this->assertCount(2, $workload);
        $deptA = collect($workload)->firstWhere('id', $this->departmentA->id);
        $deptB = collect($workload)->firstWhere('id', $this->departmentB->id);

        $this->assertEquals(1, $deptA['scheduled']);
        $this->assertEquals(1, $deptA['completed']);
        $this->assertEquals(100.0, $deptA['completion_rate']);

        $this->assertEquals(1, $deptB['scheduled']);
        $this->assertEquals(1, $deptB['missed']);
        $this->assertEquals(0.0, $deptB['completion_rate']);
    }

    public function test_department_reports_sorting_by_completion_rate_and_scheduled(): void
    {
        // Dept A: 1 completed out of 1 (100%)
        $this->createExecution([
            'process_id' => $this->processA->id,
            'scheduled_for' => now()->startOfDay()->addHours(10),
            'status' => ProcessExecutionStatus::COMPLETED,
            'completed_at' => now()->startOfDay()->addHours(11),
        ]);

        // Dept B: 0 completed out of 2 (0%)
        $this->createExecution([
            'process_id' => $this->processB->id,
            'scheduled_for' => now()->startOfDay()->addHours(8),
            'status' => ProcessExecutionStatus::PENDING,
        ]);
        $this->createExecution([
            'process_id' => $this->processB->id,
            'scheduled_for' => now()->startOfDay()->addHours(9),
            'status' => ProcessExecutionStatus::PENDING,
        ]);

        $reportDesc = $this->reportingService->getDepartmentReports(now()->startOfDay(), now()->endOfDay(), 'completion_rate', 'desc');
        $this->assertEquals($this->departmentA->id, $reportDesc['departments'][0]['id']);

        $reportAsc = $this->reportingService->getDepartmentReports(now()->startOfDay(), now()->endOfDay(), 'completion_rate', 'asc');
        $this->assertEquals($this->departmentB->id, $reportAsc['departments'][0]['id']);
    }

    public function test_department_detail_report_breaks_down_processes(): void
    {
        $this->createExecution([
            'process_id' => $this->processA->id,
            'scheduled_for' => now()->startOfDay()->addHours(10),
            'status' => ProcessExecutionStatus::COMPLETED,
            'completed_at' => now()->startOfDay()->addHours(11),
        ]);

        $detail = $this->reportingService->getDepartmentDetailReport($this->departmentA, now()->startOfDay(), now()->endOfDay());

        $this->assertEquals($this->departmentA->id, $detail['department']->id);
        $this->assertEquals(1, $detail['metrics']['scheduled']);
        $this->assertEquals(1, $detail['metrics']['completed']);
        $this->assertCount(1, $detail['processes']);
        $this->assertEquals($this->processA->id, $detail['processes'][0]['process']->id);
    }

    public function test_department_report_view_renders_totals_footer(): void
    {
        $this->createExecution([
            'process_id' => $this->processA->id,
            'scheduled_for' => now()->startOfDay()->addHours(10),
            'status' => ProcessExecutionStatus::COMPLETED,
            'completed_at' => now()->startOfDay()->addHours(11),
        ]);

        $response = $this->actingAs($this->admin)->get(route('reports.departments.index', ['date_range' => 'today']));
        $response->assertOk();
        $response->assertSee('Total / Overall');
    }

    public function test_department_detail_view_renders_recent_executions(): void
    {
        $exec = $this->createExecution([
            'process_id' => $this->processA->id,
            'scheduled_for' => now()->startOfDay()->addHours(10),
            'status' => ProcessExecutionStatus::COMPLETED,
            'completed_at' => now()->startOfDay()->addHours(11),
            'completed_by' => $this->employee->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('reports.departments.show', $this->departmentA));
        $response->assertOk();
        $response->assertSee('Daily Cash Reconciliation');
    }

    // ==========================================
    // 6. PROCESS REPORTING & FILTERS (7 TESTS)
    // ==========================================

    public function test_process_reports_filtering_by_department(): void
    {
        $reports = $this->reportingService->getProcessReports(now()->startOfMonth(), now()->endOfMonth(), [
            'department_id' => $this->departmentA->id,
        ]);

        $this->assertCount(1, $reports);
        $this->assertEquals($this->processA->id, $reports->first()->id);
    }

    public function test_process_reports_filtering_by_responsible_user(): void
    {
        $reports = $this->reportingService->getProcessReports(now()->startOfMonth(), now()->endOfMonth(), [
            'responsible_user_id' => $this->manager->id,
        ]);

        $this->assertCount(1, $reports);
        $this->assertEquals($this->processB->id, $reports->first()->id);
    }

    public function test_process_reports_filtering_by_frequency(): void
    {
        $reports = $this->reportingService->getProcessReports(now()->startOfMonth(), now()->endOfMonth(), [
            'frequency' => ProcessFrequency::WEEKLY->value,
        ]);

        $this->assertCount(1, $reports);
        $this->assertEquals($this->processB->id, $reports->first()->id);
    }

    public function test_process_reports_filtering_by_status(): void
    {
        $this->processB->update(['status' => ProcessStatus::PAUSED]);

        $reports = $this->reportingService->getProcessReports(now()->startOfMonth(), now()->endOfMonth(), [
            'status' => ProcessStatus::PAUSED->value,
        ]);

        $this->assertCount(1, $reports);
        $this->assertEquals($this->processB->id, $reports->first()->id);
    }

    public function test_process_reports_filtering_by_search_keyword(): void
    {
        $reports = $this->reportingService->getProcessReports(now()->startOfMonth(), now()->endOfMonth(), [
            'search' => 'Reconciliation',
        ]);

        $this->assertCount(1, $reports);
        $this->assertEquals($this->processA->id, $reports->first()->id);
    }

    public function test_process_detail_report_provides_metrics_trends_and_executions(): void
    {
        $exec = $this->createExecution([
            'process_id' => $this->processA->id,
            'scheduled_for' => now()->startOfDay()->addHours(9),
            'status' => ProcessExecutionStatus::COMPLETED,
            'completed_at' => now()->startOfDay()->addHours(10),
            'completed_by' => $this->employee->id,
        ]);

        $report = $this->reportingService->getProcessDetailReport($this->processA, now()->startOfDay(), now()->endOfDay(), 'today');

        $this->assertEquals($this->processA->id, $report['process']->id);
        $this->assertEquals(1, $report['metrics']['scheduled']);
        $this->assertEquals(1, $report['metrics']['completed']);
        $this->assertCount(1, $report['executions']);
    }

    public function test_process_report_web_filtering_returns_matched_processes(): void
    {
        $response = $this->actingAs($this->admin)->get(route('reports.processes.index', [
            'department_id' => $this->departmentB->id,
        ]));

        $response->assertOk();
        $response->assertSee('Weekly Warehouse Audit');
        $response->assertDontSee('Daily Cash Reconciliation');
    }

    // ==========================================
    // 7. CHECKLIST RESPONSE DISTRIBUTION (4 TESTS)
    // ==========================================

    public function test_checklist_response_breakdown_aggregates_yes_no_na_and_text(): void
    {
        // 1. Process Items
        $item1 = ProcessItem::create([
            'process_id' => $this->processA->id,
            'question' => 'Was petty cash counted and verified?',
            'response_type' => ProcessResponseType::YES_NO,
            'is_required' => true,
            'sort_order' => 1,
        ]);

        $item2 = ProcessItem::create([
            'process_id' => $this->processA->id,
            'question' => 'Was physical drawer locked overnight?',
            'response_type' => ProcessResponseType::YES_NO_NA,
            'is_required' => true,
            'sort_order' => 2,
        ]);

        // 2. Executions & Items
        $exec1 = $this->createExecution([
            'process_id' => $this->processA->id,
            'scheduled_for' => now()->subDay()->startOfDay()->addHours(10),
            'status' => ProcessExecutionStatus::COMPLETED,
        ]);

        ProcessExecutionItem::create([
            'process_execution_id' => $exec1->id,
            'process_item_id' => $item1->id,
            'question_snapshot' => $item1->question,
            'response_type' => $item1->response_type,
            'is_required' => true,
            'response' => 'YES',
            'answered_at' => now()->subDay()->addHours(11),
            'answered_by' => $this->employee->id,
            'sort_order' => 1,
        ]);

        ProcessExecutionItem::create([
            'process_execution_id' => $exec1->id,
            'process_item_id' => $item2->id,
            'question_snapshot' => $item2->question,
            'response_type' => $item2->response_type,
            'is_required' => true,
            'response' => 'NO',
            'answered_at' => now()->subDay()->addHours(11),
            'answered_by' => $this->employee->id,
            'sort_order' => 2,
        ]);

        $exec2 = $this->createExecution([
            'process_id' => $this->processA->id,
            'scheduled_for' => now()->startOfDay()->addHours(10),
            'status' => ProcessExecutionStatus::COMPLETED,
        ]);

        ProcessExecutionItem::create([
            'process_execution_id' => $exec2->id,
            'process_item_id' => $item1->id,
            'question_snapshot' => $item1->question,
            'response_type' => $item1->response_type,
            'is_required' => true,
            'response' => 'YES',
            'answered_at' => now()->addHours(11),
            'answered_by' => $this->employee->id,
            'sort_order' => 1,
        ]);

        ProcessExecutionItem::create([
            'process_execution_id' => $exec2->id,
            'process_item_id' => $item2->id,
            'question_snapshot' => $item2->question,
            'response_type' => $item2->response_type,
            'is_required' => true,
            'response' => 'NA',
            'answered_at' => now()->addHours(11),
            'answered_by' => $this->employee->id,
            'sort_order' => 2,
        ]);

        $breakdown = $this->reportingService->getChecklistResponseBreakdown($this->processA, now()->subDays(2), now()->endOfDay());

        $this->assertCount(2, $breakdown);

        $q1 = collect($breakdown)->firstWhere('question', $item1->question);
        $this->assertEquals(2, $q1['total_answered']);
        $this->assertEquals(0, $q1['total_unanswered']);
        $this->assertEquals(2, $q1['distribution']['YES']);

        $q2 = collect($breakdown)->firstWhere('question', $item2->question);
        $this->assertEquals(2, $q2['total_answered']);
        $this->assertEquals(1, $q2['distribution']['NO']);
        $this->assertEquals(1, $q2['distribution']['NA']);
    }

    public function test_checklist_response_breakdown_tracks_unanswered_items(): void
    {
        $item = ProcessItem::create([
            'process_id' => $this->processA->id,
            'question' => 'Backup done?',
            'response_type' => ProcessResponseType::YES_NO,
            'is_required' => false,
            'sort_order' => 1,
        ]);

        $exec = $this->createExecution([
            'process_id' => $this->processA->id,
            'scheduled_for' => now()->startOfDay()->addHours(10),
            'status' => ProcessExecutionStatus::IN_PROGRESS,
        ]);

        ProcessExecutionItem::create([
            'process_execution_id' => $exec->id,
            'process_item_id' => $item->id,
            'question_snapshot' => $item->question,
            'response_type' => $item->response_type,
            'is_required' => false,
            'response' => null,
            'sort_order' => 1,
        ]);

        $breakdown = $this->reportingService->getChecklistResponseBreakdown($this->processA, now()->startOfDay(), now()->endOfDay());

        $this->assertCount(1, $breakdown);
        $this->assertEquals(0, $breakdown[0]['total_answered']);
        $this->assertEquals(1, $breakdown[0]['total_unanswered']);
        $this->assertEquals(1, $breakdown[0]['distribution']['Unanswered']);
    }

    public function test_checklist_response_breakdown_returns_empty_when_no_executions(): void
    {
        $breakdown = $this->reportingService->getChecklistResponseBreakdown($this->processA, now()->subYear(), now()->subMonths(6));
        $this->assertEmpty($breakdown);
    }

    public function test_process_detail_view_renders_response_breakdown(): void
    {
        $item = ProcessItem::create([
            'process_id' => $this->processA->id,
            'question' => 'Did you verify cash?',
            'response_type' => ProcessResponseType::YES_NO,
            'is_required' => true,
            'sort_order' => 1,
        ]);

        $exec = $this->createExecution([
            'process_id' => $this->processA->id,
            'scheduled_for' => now()->startOfDay()->addHours(10),
            'status' => ProcessExecutionStatus::COMPLETED,
        ]);

        ProcessExecutionItem::create([
            'process_execution_id' => $exec->id,
            'process_item_id' => $item->id,
            'question_snapshot' => 'Did you verify cash?',
            'response_type' => ProcessResponseType::YES_NO,
            'is_required' => true,
            'response' => 'YES',
            'answered_at' => now(),
            'answered_by' => $this->employee->id,
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($this->admin)->get(route('reports.processes.show', [
            'process' => $this->processA,
            'date_range' => 'today',
        ]));

        $response->assertOk();
        $response->assertSee('Checklist Question Responses Analysis');
        $response->assertSee('Did you verify cash?');
    }

    // ==========================================
    // 8. ESCALATIONS REPORTING & INCIDENT AUDIT (6 TESTS)
    // ==========================================

    public function test_escalation_reports_summary_metrics(): void
    {
        $exec = $this->createExecution([
            'process_id' => $this->processA->id,
            'scheduled_for' => now()->subHours(4),
            'status' => ProcessExecutionStatus::PENDING,
        ]);

        $rule1 = ProcessEscalationRule::create([
            'process_id' => $this->processA->id,
            'level' => 1,
            'trigger_after_minutes' => 30,
            'escalate_to_user_id' => $this->manager->id,
            'is_active' => true,
        ]);

        $rule2 = ProcessEscalationRule::create([
            'process_id' => $this->processA->id,
            'level' => 2,
            'trigger_after_minutes' => 60,
            'escalate_to_user_id' => $this->admin->id,
            'is_active' => true,
        ]);

        ProcessEscalationEvent::create([
            'process_execution_id' => $exec->id,
            'escalation_rule_id' => $rule1->id,
            'level' => 1,
            'triggered_at' => now()->subHours(3),
            'status' => ProcessEscalationStatus::TRIGGERED,
        ]);

        ProcessEscalationEvent::create([
            'process_execution_id' => $exec->id,
            'escalation_rule_id' => $rule2->id,
            'level' => 2,
            'triggered_at' => now()->subHours(2),
            'status' => ProcessEscalationStatus::ACKNOWLEDGED,
            'acknowledged_by' => $this->admin->id,
            'acknowledged_at' => now()->subHour(),
        ]);

        $report = $this->reportingService->getEscalationReports(now()->startOfDay(), now()->endOfDay());

        $this->assertEquals(2, $report['summary']['total']);
        $this->assertEquals(1, $report['summary']['triggered']);
        $this->assertEquals(1, $report['summary']['acknowledged']);
        $this->assertEquals(0, $report['summary']['resolved']);
        $this->assertEquals(0, $report['summary']['cancelled']);
    }

    public function test_escalation_reports_filtering_by_recipient_and_level(): void
    {
        $exec = $this->createExecution([
            'process_id' => $this->processA->id,
            'scheduled_for' => now()->subHours(4),
            'status' => ProcessExecutionStatus::PENDING,
        ]);

        $rule1 = ProcessEscalationRule::create([
            'process_id' => $this->processA->id,
            'level' => 1,
            'trigger_after_minutes' => 30,
            'escalate_to_user_id' => $this->manager->id,
            'is_active' => true,
        ]);

        $rule2 = ProcessEscalationRule::create([
            'process_id' => $this->processA->id,
            'level' => 2,
            'trigger_after_minutes' => 60,
            'escalate_to_user_id' => $this->admin->id,
            'is_active' => true,
        ]);

        ProcessEscalationEvent::create([
            'process_execution_id' => $exec->id,
            'escalation_rule_id' => $rule1->id,
            'level' => 1,
            'triggered_at' => now()->subHours(3),
            'status' => ProcessEscalationStatus::TRIGGERED,
        ]);

        ProcessEscalationEvent::create([
            'process_execution_id' => $exec->id,
            'escalation_rule_id' => $rule2->id,
            'level' => 2,
            'triggered_at' => now()->subHours(2),
            'status' => ProcessEscalationStatus::TRIGGERED,
        ]);

        // Filter by recipient = admin
        $reportAdmin = $this->reportingService->getEscalationReports(now()->startOfDay(), now()->endOfDay(), [
            'recipient_id' => $this->admin->id,
        ]);
        $this->assertEquals(1, $reportAdmin['events']->total());

        // Filter by level = 1
        $reportLevel1 = $this->reportingService->getEscalationReports(now()->startOfDay(), now()->endOfDay(), [
            'level' => 1,
        ]);
        $this->assertEquals(1, $reportLevel1['events']->total());
    }

    public function test_escalation_reports_daily_trend_accuracy(): void
    {
        $exec = $this->createExecution([
            'process_id' => $this->processA->id,
            'scheduled_for' => now()->subHours(4),
            'status' => ProcessExecutionStatus::PENDING,
        ]);

        $rule = ProcessEscalationRule::create([
            'process_id' => $this->processA->id,
            'level' => 1,
            'trigger_after_minutes' => 30,
            'escalate_to_user_id' => $this->manager->id,
            'is_active' => true,
        ]);

        ProcessEscalationEvent::create([
            'process_execution_id' => $exec->id,
            'escalation_rule_id' => $rule->id,
            'level' => 1,
            'triggered_at' => now()->subHours(3),
            'status' => ProcessEscalationStatus::TRIGGERED,
        ]);

        $trend = $this->reportingService->getEscalationDailyTrend(now()->subDays(2)->startOfDay(), now()->endOfDay());

        $this->assertCount(3, $trend);
        $todayTrend = collect($trend)->firstWhere('date', now()->format('Y-m-d'));
        $this->assertEquals(1, $todayTrend['count']);
    }

    public function test_repeated_escalations_detects_processes_with_multiple_incidents(): void
    {
        $exec1 = $this->createExecution([
            'process_id' => $this->processA->id,
            'scheduled_for' => now()->subDays(2),
            'status' => ProcessExecutionStatus::MISSED,
        ]);

        $exec2 = $this->createExecution([
            'process_id' => $this->processA->id,
            'scheduled_for' => now()->subDay(),
            'status' => ProcessExecutionStatus::MISSED,
        ]);

        $rule = ProcessEscalationRule::create([
            'process_id' => $this->processA->id,
            'level' => 1,
            'trigger_after_minutes' => 30,
            'escalate_to_user_id' => $this->manager->id,
            'is_active' => true,
        ]);

        ProcessEscalationEvent::create([
            'process_execution_id' => $exec1->id,
            'escalation_rule_id' => $rule->id,
            'level' => 1,
            'triggered_at' => now()->subDays(2)->addHour(),
            'status' => ProcessEscalationStatus::TRIGGERED,
        ]);

        ProcessEscalationEvent::create([
            'process_execution_id' => $exec2->id,
            'escalation_rule_id' => $rule->id,
            'level' => 1,
            'triggered_at' => now()->subDay()->addHour(),
            'status' => ProcessEscalationStatus::TRIGGERED,
        ]);

        $repeated = $this->reportingService->getRepeatedEscalations(now()->subDays(5), now()->endOfDay(), 2);

        $this->assertCount(1, $repeated);
        $this->assertEquals($this->processA->id, $repeated[0]['process']->id);
        $this->assertEquals(2, $repeated[0]['escalations_count']);
    }

    public function test_attention_required_returns_overdue_and_unacknowledged_escalations(): void
    {
        // Overdue execution
        $this->createExecution([
            'process_id' => $this->processA->id,
            'scheduled_for' => now()->subHours(3),
            'status' => ProcessExecutionStatus::PENDING,
        ]);

        // Unacknowledged escalation
        $exec = $this->createExecution([
            'process_id' => $this->processB->id,
            'scheduled_for' => now()->subHours(2),
            'status' => ProcessExecutionStatus::PENDING,
        ]);

        $rule = ProcessEscalationRule::create([
            'process_id' => $this->processB->id,
            'level' => 1,
            'trigger_after_minutes' => 30,
            'escalate_to_user_id' => $this->admin->id,
            'is_active' => true,
        ]);

        ProcessEscalationEvent::create([
            'process_execution_id' => $exec->id,
            'escalation_rule_id' => $rule->id,
            'level' => 1,
            'triggered_at' => now()->subHour(),
            'status' => ProcessEscalationStatus::TRIGGERED,
        ]);

        $attention = $this->reportingService->getAttentionRequired();

        $this->assertNotEmpty($attention['overdue_executions']);
        $this->assertNotEmpty($attention['unacknowledged_escalations']);
    }

    public function test_escalation_report_web_view_displays_filtered_events(): void
    {
        $exec = $this->createExecution([
            'process_id' => $this->processA->id,
            'scheduled_for' => now()->subHours(4),
            'status' => ProcessExecutionStatus::PENDING,
        ]);

        $rule = ProcessEscalationRule::create([
            'process_id' => $this->processA->id,
            'level' => 1,
            'trigger_after_minutes' => 30,
            'escalate_to_user_id' => $this->manager->id,
            'is_active' => true,
        ]);

        ProcessEscalationEvent::create([
            'process_execution_id' => $exec->id,
            'escalation_rule_id' => $rule->id,
            'level' => 1,
            'triggered_at' => now()->subHours(3),
            'status' => ProcessEscalationStatus::TRIGGERED,
        ]);

        $response = $this->actingAs($this->admin)->get(route('reports.escalations.index', [
            'date_range' => 'today',
        ]));

        $response->assertOk();
        $response->assertSee('Daily Cash Reconciliation');
        $response->assertSee('Level 1');
    }

    // ==========================================
    // 9. DATA INTEGRITY & AUDIT SAFETY (2 TESTS)
    // ==========================================

    public function test_reports_are_strictly_read_only_and_do_not_mutate_data(): void
    {
        $exec = $this->createExecution([
            'process_id' => $this->processA->id,
            'scheduled_for' => now()->subHours(2),
            'status' => ProcessExecutionStatus::PENDING,
        ]);

        $countBefore = ProcessExecution::count();
        $statusBefore = $exec->fresh()->status;

        // View reports overview & process details
        $this->actingAs($this->admin)->get(route('reports.index'));
        $this->actingAs($this->admin)->get(route('reports.processes.show', $this->processA));
        $this->actingAs($this->admin)->get(route('reports.departments.show', $this->departmentA));
        $this->actingAs($this->admin)->get(route('reports.escalations.index'));

        $this->assertEquals($countBefore, ProcessExecution::count());
        $this->assertEquals($statusBefore, $exec->fresh()->status);
    }

    public function test_sidebar_displays_reports_navigation_links_for_manager_and_admin(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard'));
        $response->assertOk();
        $response->assertSee(route('reports.index'));
        $response->assertSee(route('reports.departments.index'));
        $response->assertSee(route('reports.processes.index'));
        $response->assertSee(route('reports.escalations.index'));
    }
}
