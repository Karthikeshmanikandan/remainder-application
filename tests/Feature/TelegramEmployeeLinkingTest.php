<?php

namespace Tests\Feature;

use App\Enums\ProcessResponseType;
use App\Enums\ProcessStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Organization;
use App\Models\Process;
use App\Models\ProcessItem;
use App\Models\Project;
use App\Models\Task;
use App\Models\TelegramEmployee;
use App\Models\User;
use App\Services\ProcessScheduleService;
use App\Services\TaskService;
use App\Services\TelegramAccountService;
use App\Services\TelegramService;
use App\Services\TelegramUpdateService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramEmployeeLinkingTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;

    private Organization $orgB;

    private User $adminA;

    private User $managerA;

    private User $adminB;

    private Department $deptA;

    private Department $deptB;

    private Project $projectA;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
        ]);

        Config::set('services.telegram.bot_token', '123456:FAKE_TOKEN');
        Config::set('services.telegram.bot_username', 'AcmeCompanyBot');

        $this->orgA = Organization::create([
            'name' => 'Acme Corp A',
            'code' => 'ORG-A',
            'slug' => 'acme-corp-a',
            'max_web_users' => 5,
            'is_active' => true,
        ]);

        $this->orgB = Organization::create([
            'name' => 'Beta Corp B',
            'code' => 'ORG-B',
            'slug' => 'beta-corp-b',
            'max_web_users' => 5,
            'is_active' => true,
        ]);

        $this->adminA = User::factory()->create([
            'organization_id' => $this->orgA->id,
            'role' => UserRole::ADMIN,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->managerA = User::factory()->create([
            'organization_id' => $this->orgA->id,
            'role' => UserRole::MANAGER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->adminB = User::factory()->create([
            'organization_id' => $this->orgB->id,
            'role' => UserRole::ADMIN,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->deptA = Department::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Logistics A',
            'code' => 'LOG-A',
            'is_active' => true,
        ]);

        $this->deptB = Department::create([
            'organization_id' => $this->orgB->id,
            'name' => 'Logistics B',
            'code' => 'LOG-B',
            'is_active' => true,
        ]);

        $this->projectA = Project::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Warehouse Ops',
            'code' => 'PRJ-WH-01',
            'created_by' => $this->adminA->id,
        ]);
    }

    // 1. Telegram employee can be created without chat_id
    public function test_telegram_employee_can_be_created_without_chat_id(): void
    {
        $response = $this->actingAs($this->adminA)->post(route('admin.telegram-employees.store'), [
            'name' => 'Rahul Sharma',
            'employee_code' => 'EMP-1001',
            'phone' => '+91 9876543210',
            'department_id' => $this->deptA->id,
            'notes' => 'Warehouse dispatch staff',
            'is_active' => 1,
        ]);

        $response->assertRedirect();
        $employee = TelegramEmployee::where('employee_code', 'EMP-1001')->first();
        $this->assertNotNull($employee);
        $this->assertEquals('Rahul Sharma', $employee->name);
        $this->assertNull($employee->telegramAccount);
        $this->assertFalse($employee->isTelegramConnected());
    }

    // 2. Phone number is stored as employee profile data
    public function test_phone_number_is_stored_as_employee_profile_data(): void
    {
        $employee = TelegramEmployee::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Pooja Patel',
            'employee_code' => 'EMP-1002',
            'phone' => '+91 9123456780',
            'is_active' => true,
        ]);

        $this->assertEquals('+91 9123456780', $employee->phone);
    }

    // 3. Chat ID is initially null/unlinked
    public function test_chat_id_is_initially_null_and_unlinked(): void
    {
        $employee = TelegramEmployee::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Vikram Singh',
            'employee_code' => 'EMP-1003',
            'is_active' => true,
        ]);

        $this->assertNull($employee->telegramAccount);
        $this->assertFalse($employee->isTelegramConnected());
    }

    // 4. Admin can generate Telegram linking code
    public function test_admin_can_generate_telegram_linking_code(): void
    {
        $employee = TelegramEmployee::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Amit Verma',
            'employee_code' => 'EMP-1004',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminA)->post(route('admin.telegram-employees.generate-code', $employee));
        $response->assertRedirect();
        $response->assertSessionHas('linking_code');

        $account = $employee->fresh()->telegramAccount;
        $this->assertNotNull($account);
        $this->assertStringStartsWith('TGEMP-', $account->verification_code);
        $this->assertNotNull($account->verification_expires_at);
        $this->assertFalse($account->isVerified());
    }

    // 5. Linking code expires after configured lifetime (15 min)
    public function test_linking_code_expires_after_15_minutes(): void
    {
        $employee = TelegramEmployee::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Sunil Joshi',
            'employee_code' => 'EMP-1005',
            'is_active' => true,
        ]);

        $accountService = app(TelegramAccountService::class);
        $code = $accountService->generateCodeForTelegramEmployee($employee);

        $account = $employee->fresh()->telegramAccount;
        $this->assertFalse($account->isVerificationCodeExpired());

        // Fast forward 16 minutes
        Carbon::setTestNow(now()->addMinutes(16));
        $this->assertTrue($account->fresh()->isVerificationCodeExpired());

        $linked = $accountService->verifyCodeAndLink($code, '99887766');
        $this->assertNull($linked);

        Carbon::setTestNow();
    }

    // 6. Linking code is one-time and cannot be reused
    public function test_linking_code_is_one_time_and_cannot_be_reused(): void
    {
        $employee = TelegramEmployee::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Meera Nair',
            'employee_code' => 'EMP-1006',
            'is_active' => true,
        ]);

        $accountService = app(TelegramAccountService::class);
        $code = $accountService->generateCodeForTelegramEmployee($employee);

        // First verification succeeds
        $firstAttempt = $accountService->verifyCodeAndLink($code, '55443322');
        $this->assertNotNull($firstAttempt);
        $this->assertNull($firstAttempt->verification_code);

        // Second verification attempt with same code must fail
        $secondAttempt = $accountService->verifyCodeAndLink($code, '11223344');
        $this->assertNull($secondAttempt);
    }

    // 7. Deep link contains the linking payload
    public function test_deep_link_contains_the_linking_payload(): void
    {
        $telegramService = app(TelegramService::class);
        $deepLink = $telegramService->getDeepLink('TGEMP-A8K92X');

        $this->assertEquals('https://t.me/AcmeCompanyBot?start=TGEMP-A8K92X', $deepLink);
    }

    // 8. Telegram /start update is processed
    // 9. Telegram chat.id is automatically captured
    // 10. Telegram user ID is captured
    // 11. Telegram username is captured when available
    // 12. Employee becomes linked
    // 13. Telegram account points to employee
    public function test_telegram_start_command_with_deep_link_code_links_employee(): void
    {
        $employee = TelegramEmployee::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Deepak Rawat',
            'employee_code' => 'EMP-1007',
            'is_active' => true,
        ]);

        $accountService = app(TelegramAccountService::class);
        $code = $accountService->generateCodeForTelegramEmployee($employee);

        $updateService = app(TelegramUpdateService::class);
        $updateService->handleIncomingText(
            chatId: '88776655',
            telegramUserId: '88776655',
            username: 'deepak_r',
            text: "/start {$code}"
        );

        $account = $employee->fresh()->telegramAccount;
        $this->assertNotNull($account);
        $this->assertTrue($account->isVerified());
        $this->assertEquals('88776655', $account->chat_id);
        $this->assertEquals('88776655', $account->telegram_user_id);
        $this->assertEquals('deepak_r', $account->telegram_username);
        $this->assertEquals($employee->id, $account->telegram_employee_id);
        $this->assertNull($account->user_id);
        $this->assertEquals('deepak_r', $employee->fresh()->telegram_username);
    }

    // 14. Employee receives confirmation in Telegram
    public function test_employee_receives_confirmation_message_in_telegram(): void
    {
        $employee = TelegramEmployee::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Kavita Das',
            'employee_code' => 'EMP-1008',
            'is_active' => true,
        ]);

        $accountService = app(TelegramAccountService::class);
        $code = $accountService->generateCodeForTelegramEmployee($employee);

        $updateService = app(TelegramUpdateService::class);
        $updateService->handleIncomingText(
            chatId: '33445566',
            telegramUserId: '33445566',
            username: 'kavita_das',
            text: "/start {$code}"
        );

        Http::assertSent(function ($request) {
            return str_contains($request['chat_id'], '33445566') &&
                   str_contains($request['text'], 'Telegram connected successfully');
        });
    }

    // 15. Invalid code is rejected
    public function test_invalid_code_is_rejected(): void
    {
        $updateService = app(TelegramUpdateService::class);
        $updateService->handleIncomingText(
            chatId: '99991111',
            telegramUserId: '99991111',
            username: 'random_user',
            text: '/start TGEMP-INVALID'
        );

        Http::assertSent(function ($request) {
            return str_contains($request['text'], 'invalid or has expired');
        });
    }

    // 16. Expired code is rejected
    public function test_expired_code_is_rejected_via_update_service(): void
    {
        $employee = TelegramEmployee::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Rohan Gupta',
            'employee_code' => 'EMP-1009',
            'is_active' => true,
        ]);

        $accountService = app(TelegramAccountService::class);
        $code = $accountService->generateCodeForTelegramEmployee($employee);

        Carbon::setTestNow(now()->addMinutes(20));

        $updateService = app(TelegramUpdateService::class);
        $updateService->handleIncomingText(
            chatId: '12312312',
            telegramUserId: '12312312',
            username: 'rohan_g',
            text: "/start {$code}"
        );

        $this->assertFalse($employee->fresh()->isTelegramConnected());

        Http::assertSent(function ($request) {
            return str_contains($request['text'], 'invalid or has expired');
        });

        Carbon::setTestNow();
    }

    // 17. Inactive employee cannot link
    public function test_inactive_employee_cannot_link_telegram_account(): void
    {
        $employee = TelegramEmployee::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Inactive Worker',
            'employee_code' => 'EMP-INACTIVE',
            'is_active' => false,
        ]);

        $account = $employee->telegramAccount()->create([
            'verification_code' => 'TGEMP-INACTV',
            'verification_expires_at' => now()->addMinutes(15),
        ]);

        $updateService = app(TelegramUpdateService::class);
        $updateService->handleIncomingText(
            chatId: '77665544',
            telegramUserId: '77665544',
            username: 'inactive_tg',
            text: '/start TGEMP-INACTV'
        );

        $this->assertFalse($employee->fresh()->isTelegramConnected());

        Http::assertSent(function ($request) {
            return str_contains($request['text'], 'account is currently inactive');
        });
    }

    // 18. Telegram account cannot belong to two identities
    public function test_telegram_chat_cannot_be_linked_to_two_different_identities(): void
    {
        // First employee is already linked to chat 55555555
        $employeeA = TelegramEmployee::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Emp A',
            'employee_code' => 'EMP-A',
            'is_active' => true,
        ]);
        $employeeA->telegramAccount()->create([
            'chat_id' => '55555555',
            'telegram_user_id' => '55555555',
            'verified_at' => now(),
            'is_active' => true,
        ]);

        // Second employee generates code
        $employeeB = TelegramEmployee::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Emp B',
            'employee_code' => 'EMP-B',
            'is_active' => true,
        ]);
        $accountService = app(TelegramAccountService::class);
        $codeB = $accountService->generateCodeForTelegramEmployee($employeeB);

        // Attempting to link the same chat 55555555 with Employee B's code must fail
        $updateService = app(TelegramUpdateService::class);
        $updateService->handleIncomingText(
            chatId: '55555555',
            telegramUserId: '55555555',
            username: 'emp_b',
            text: "/start {$codeB}"
        );

        $this->assertFalse($employeeB->fresh()->isTelegramConnected());
        $this->assertTrue($employeeA->fresh()->isTelegramConnected());

        Http::assertSent(function ($request) {
            return str_contains($request['text'], 'already connected');
        });
    }

    // 19. Cross-organization linking is impossible & IDOR attempts are blocked
    public function test_cross_organization_idor_attempts_are_blocked(): void
    {
        $employeeOrgB = TelegramEmployee::create([
            'organization_id' => $this->orgB->id,
            'name' => 'Beta Worker',
            'employee_code' => 'EMP-BETA-1',
            'is_active' => true,
        ]);

        // Admin A from Org A tries to generate code for employee in Org B -> 403
        $response = $this->actingAs($this->adminA)->post(route('admin.telegram-employees.generate-code', $employeeOrgB));
        $response->assertStatus(403);

        // Admin A tries to unlink employee in Org B -> 403
        $response = $this->actingAs($this->adminA)->post(route('admin.telegram-employees.unlink', $employeeOrgB));
        $response->assertStatus(403);
    }

    // 20. Unlink works and preserves employee record & history
    public function test_unlink_works_and_preserves_employee_and_task_history(): void
    {
        $employee = TelegramEmployee::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Sanjay Dutt',
            'employee_code' => 'EMP-1010',
            'phone' => '+91 9988776655',
            'is_active' => true,
        ]);

        $account = $employee->telegramAccount()->create([
            'chat_id' => '12121212',
            'telegram_user_id' => '12121212',
            'telegram_username' => 'sanjay_d',
            'verified_at' => now(),
            'is_active' => true,
        ]);

        $task = Task::create([
            'organization_id' => $this->orgA->id,
            'project_id' => $this->projectA->id,
            'title' => 'Deliver equipment',
            'code' => 'TSK-9901',
            'assigned_telegram_employee_id' => $employee->id,
            'created_by' => $this->adminA->id,
        ]);

        $this->assertTrue($employee->isTelegramConnected());

        // Admin unlinks account
        $response = $this->actingAs($this->adminA)->post(route('admin.telegram-employees.unlink', $employee));
        $response->assertRedirect();

        $freshEmployee = $employee->fresh();
        $this->assertNotNull($freshEmployee);
        $this->assertEquals('Sanjay Dutt', $freshEmployee->name);
        $this->assertEquals('+91 9988776655', $freshEmployee->phone);
        $this->assertFalse($freshEmployee->isTelegramConnected());
        $this->assertNull($freshEmployee->telegramAccount->chat_id);
        $this->assertNull($freshEmployee->telegramAccount->verified_at);

        // Task remains assigned to the employee
        $this->assertEquals($employee->id, $task->fresh()->assigned_telegram_employee_id);
    }

    // 21. New link can be generated after unlink
    public function test_new_link_can_be_generated_after_unlink(): void
    {
        $employee = TelegramEmployee::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Alok Nath',
            'employee_code' => 'EMP-1011',
            'is_active' => true,
        ]);

        $accountService = app(TelegramAccountService::class);
        $code1 = $accountService->generateCodeForTelegramEmployee($employee);
        $accountService->verifyCodeAndLink($code1, '77112233');

        $this->assertTrue($employee->fresh()->isTelegramConnected());

        // Unlink
        $accountService->unlinkEmployeeAccount($employee);
        $this->assertFalse($employee->fresh()->isTelegramConnected());

        // Generate new link
        $code2 = $accountService->generateCodeForTelegramEmployee($employee);
        $this->assertNotEquals($code1, $code2);

        // Link with new chat
        $linked = $accountService->verifyCodeAndLink($code2, '88223344');
        $this->assertNotNull($linked);
        $this->assertTrue($employee->fresh()->isTelegramConnected());
        $this->assertEquals('88223344', $employee->fresh()->telegramAccount->chat_id);
    }

    // 22. Phone number is never used as chat_id
    public function test_phone_number_is_independent_and_never_used_as_chat_id(): void
    {
        $employee = TelegramEmployee::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Nitin Gadkari',
            'employee_code' => 'EMP-1012',
            'phone' => '+91 9988771122',
            'is_active' => true,
        ]);

        $accountService = app(TelegramAccountService::class);
        $code = $accountService->generateCodeForTelegramEmployee($employee);
        $accountService->verifyCodeAndLink($code, '44556677');

        $this->assertEquals('+91 9988771122', $employee->phone);
        $this->assertEquals('44556677', $employee->fresh()->telegramAccount->chat_id);
        $this->assertNotEquals($employee->phone, $employee->fresh()->telegramAccount->chat_id);
    }

    // 23. Task notification uses linked chat_id
    public function test_task_assignment_notification_uses_linked_chat_id(): void
    {
        $employee = TelegramEmployee::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Vijay Kumar',
            'employee_code' => 'EMP-1013',
            'is_active' => true,
        ]);

        $employee->telegramAccount()->create([
            'chat_id' => '99441122',
            'verified_at' => now(),
            'is_active' => true,
        ]);

        $task = Task::create([
            'organization_id' => $this->orgA->id,
            'project_id' => $this->projectA->id,
            'title' => 'Inspect Warehouse Site A',
            'code' => 'TSK-1013',
            'assigned_telegram_employee_id' => $employee->id,
            'created_by' => $this->adminA->id,
            'priority' => TaskPriority::HIGH,
        ]);

        $taskService = app(TaskService::class);
        $taskService->sendAssignmentNotification($task);

        Http::assertSent(function ($request) {
            return $request['chat_id'] === '99441122' &&
                   str_contains($request['text'], 'TSK-1013') &&
                   str_contains($request['text'], 'Inspect Warehouse Site A');
        });
    }

    // 24. Process notification uses linked chat_id
    public function test_process_schedule_notification_uses_linked_chat_id(): void
    {
        $employee = TelegramEmployee::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Ashok Gehlot',
            'employee_code' => 'EMP-1014',
            'is_active' => true,
        ]);

        $employee->telegramAccount()->create([
            'chat_id' => '77332211',
            'verified_at' => now(),
            'is_active' => true,
        ]);

        $process = Process::create([
            'organization_id' => $this->orgA->id,
            'department_id' => $this->deptA->id,
            'name' => 'Daily Fire Safety Checklist',
            'code' => 'PRC-FIRE-01',
            'frequency' => 'daily',
            'reminder_time' => '09:00:00',
            'status' => ProcessStatus::ACTIVE,
            'responsible_telegram_employee_id' => $employee->id,
            'created_by' => $this->adminA->id,
        ]);

        ProcessItem::create([
            'process_id' => $process->id,
            'question' => 'Are all fire extinguishers pressure gauges green?',
            'response_type' => ProcessResponseType::YES_NO,
            'order' => 1,
            'is_required' => true,
        ]);

        $scheduleService = app(ProcessScheduleService::class);
        $execution = $scheduleService->generateExecutionForDate($process, Carbon::today()->setTime(9, 0));

        $this->assertNotNull($execution);
        $this->assertEquals($employee->id, $execution->process->responsible_telegram_employee_id);

        Http::assertSent(function ($request) {
            return $request['chat_id'] === '77332211' &&
                   str_contains($request['text'], 'Daily Fire Safety Checklist') &&
                   str_contains($request['text'], 'fire extinguishers');
        });
    }

    // 25. Telegram failure does not break core task/process transactions
    public function test_telegram_api_failure_does_not_break_task_creation_or_completion(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => false, 'description' => 'Bot was blocked by user'], 403),
        ]);

        $employee = TelegramEmployee::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Manoj Bajpayee',
            'employee_code' => 'EMP-1015',
            'is_active' => true,
        ]);

        $employee->telegramAccount()->create([
            'chat_id' => '66554433',
            'verified_at' => now(),
            'is_active' => true,
        ]);

        // Creating task and sending assignment notification must not throw exception
        $task = Task::create([
            'organization_id' => $this->orgA->id,
            'project_id' => $this->projectA->id,
            'title' => 'Resilient Task',
            'code' => 'TSK-RESILIENT',
            'assigned_telegram_employee_id' => $employee->id,
            'created_by' => $this->adminA->id,
        ]);

        $taskService = app(TaskService::class);
        $taskService->sendAssignmentNotification($task);

        $this->assertDatabaseHas('tasks', ['id' => $task->id]);

        // Completing task via Telegram update service must still complete task even if reply fails
        $updateService = app(TelegramUpdateService::class);
        $updateService->handleIncomingText(
            chatId: '66554433',
            telegramUserId: '66554433',
            username: 'manoj_b',
            text: 'DONE TSK-RESILIENT'
        );

        $this->assertEquals(TaskStatus::COMPLETED, $task->fresh()->status);
    }

    // 26. Audit logs are properly recorded for linking & unlinking
    public function test_audit_logs_are_properly_created_for_telegram_events(): void
    {
        $employee = TelegramEmployee::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Govinda Ahuja',
            'employee_code' => 'EMP-1016',
            'is_active' => true,
        ]);

        $accountService = app(TelegramAccountService::class);
        $code = $accountService->generateCodeForTelegramEmployee($employee);

        $accountService->verifyCodeAndLink($code, '11992288', '11992288', 'govinda_tg');
        $accountService->unlinkEmployeeAccount($employee);

        $logs = AuditLog::where('organization_id', $this->orgA->id)->get();
        $this->assertTrue($logs->contains(fn ($log) => str_contains($log->summary, 'linking code generated')));
        $this->assertTrue($logs->contains(fn ($log) => str_contains($log->summary, 'successfully linked')));
        $this->assertTrue($logs->contains(fn ($log) => str_contains($log->summary, 'Telegram account unlinked')));
    }
}
