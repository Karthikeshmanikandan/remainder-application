<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create Organizations table
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('timezone')->default('UTC');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('max_web_users')->default(5);
            $table->timestamps();
        });

        // Insert Default Organization
        $defaultOrgId = DB::table('organizations')->insertGetId([
            'name' => 'Default Organization',
            'code' => 'DEFAULT',
            'is_active' => true,
            'max_web_users' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Create Organization Users (membership) table
        Schema::create('organization_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role')->default('employee');
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['organization_id', 'user_id']);
        });

        // 3. Add organization_id to users table
        Schema::table('users', function (Blueprint $table) use ($defaultOrgId) {
            $table->foreignId('organization_id')->nullable()->default($defaultOrgId)->after('id')->constrained('organizations')->cascadeOnDelete();
        });

        // Populate existing users with default org
        $existingUsers = DB::table('users')->get();
        foreach ($existingUsers as $user) {
            DB::table('users')->where('id', $user->id)->update(['organization_id' => $defaultOrgId]);
            DB::table('organization_users')->insertOrIgnore([
                'organization_id' => $defaultOrgId,
                'user_id' => $user->id,
                'role' => $user->role ?? 'employee',
                'status' => $user->status ?? 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 4. Create Telegram Employees table
        Schema::create('telegram_employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('employee_code');
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('telegram_username')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'employee_code']);
            $table->index(['organization_id', 'is_active']);
        });

        // 5. Update Telegram Accounts table (make user_id nullable and add telegram_employee_id)
        Schema::table('telegram_accounts', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->foreignId('telegram_employee_id')->nullable()->after('user_id')->constrained('telegram_employees')->cascadeOnDelete();
        });

        // 6. Add organization_id to departments
        Schema::table('departments', function (Blueprint $table) use ($defaultOrgId) {
            $table->foreignId('organization_id')->nullable()->default($defaultOrgId)->after('id')->constrained('organizations')->cascadeOnDelete();
        });
        DB::table('departments')->update(['organization_id' => $defaultOrgId]);

        // 7. Add organization_id to projects
        Schema::table('projects', function (Blueprint $table) use ($defaultOrgId) {
            $table->foreignId('organization_id')->nullable()->default($defaultOrgId)->after('id')->constrained('organizations')->cascadeOnDelete();
        });
        DB::table('projects')->update(['organization_id' => $defaultOrgId]);

        // 8. Add organization_id and assigned_telegram_employee_id to tasks
        Schema::table('tasks', function (Blueprint $table) use ($defaultOrgId) {
            $table->foreignId('organization_id')->nullable()->default($defaultOrgId)->after('id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->change();
            $table->foreignId('assigned_telegram_employee_id')->nullable()->after('assigned_to')->constrained('telegram_employees')->nullOnDelete();
        });
        DB::table('tasks')->update(['organization_id' => $defaultOrgId]);

        // 9. Add organization_id and assigned_telegram_employee_id to recurring_tasks
        Schema::table('recurring_tasks', function (Blueprint $table) use ($defaultOrgId) {
            $table->foreignId('organization_id')->nullable()->default($defaultOrgId)->after('id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->change();
            $table->foreignId('assigned_telegram_employee_id')->nullable()->after('assigned_to')->constrained('telegram_employees')->nullOnDelete();
        });
        DB::table('recurring_tasks')->update(['organization_id' => $defaultOrgId]);

        // 10. Add organization_id to process_templates
        Schema::table('process_templates', function (Blueprint $table) use ($defaultOrgId) {
            $table->foreignId('organization_id')->nullable()->default($defaultOrgId)->after('id')->constrained('organizations')->cascadeOnDelete();
        });
        DB::table('process_templates')->update(['organization_id' => $defaultOrgId]);

        // 11. Add organization_id and responsible_telegram_employee_id to processes
        Schema::table('processes', function (Blueprint $table) use ($defaultOrgId) {
            $table->foreignId('organization_id')->nullable()->default($defaultOrgId)->after('id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->change();
            $table->foreignId('responsible_telegram_employee_id')->nullable()->after('responsible_user_id')->constrained('telegram_employees')->nullOnDelete();
        });
        DB::table('processes')->update(['organization_id' => $defaultOrgId]);

        // 12. Add organization_id, completed_by_telegram_employee_id, snapshots to process_executions
        Schema::table('process_executions', function (Blueprint $table) use ($defaultOrgId) {
            $table->foreignId('organization_id')->nullable()->default($defaultOrgId)->after('id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('completed_by_telegram_employee_id')->nullable()->after('completed_by')->constrained('telegram_employees')->nullOnDelete();
            $table->string('responsible_name_snapshot')->nullable()->after('completed_by_telegram_employee_id');
            $table->string('responsible_type_snapshot')->nullable()->after('responsible_name_snapshot');
        });
        DB::table('process_executions')->update(['organization_id' => $defaultOrgId]);

        // 13. Add answered_by_telegram_employee_id to process_execution_items
        Schema::table('process_execution_items', function (Blueprint $table) {
            $table->foreignId('answered_by_telegram_employee_id')->nullable()->after('answered_by')->constrained('telegram_employees')->nullOnDelete();
        });

        // 14. Add organization_id and actor polymorphic columns to audit_logs
        Schema::table('audit_logs', function (Blueprint $table) use ($defaultOrgId) {
            $table->foreignId('organization_id')->nullable()->default($defaultOrgId)->after('id')->constrained('organizations')->cascadeOnDelete();
            $table->string('actor_type')->nullable()->after('user_id');
            $table->unsignedBigInteger('actor_id')->nullable()->after('actor_type');
        });
        DB::table('audit_logs')->update(['organization_id' => $defaultOrgId]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn(['organization_id', 'actor_type', 'actor_id']);
        });

        Schema::table('process_execution_items', function (Blueprint $table) {
            $table->dropForeign(['answered_by_telegram_employee_id']);
            $table->dropColumn('answered_by_telegram_employee_id');
        });

        Schema::table('process_executions', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropForeign(['completed_by_telegram_employee_id']);
            $table->dropColumn(['organization_id', 'completed_by_telegram_employee_id', 'responsible_name_snapshot', 'responsible_type_snapshot']);
        });

        Schema::table('processes', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropForeign(['responsible_telegram_employee_id']);
            $table->dropColumn(['organization_id', 'responsible_telegram_employee_id']);
        });

        Schema::table('process_templates', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropColumn('organization_id');
        });

        Schema::table('recurring_tasks', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropForeign(['assigned_telegram_employee_id']);
            $table->dropColumn(['organization_id', 'assigned_telegram_employee_id']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropForeign(['assigned_telegram_employee_id']);
            $table->dropColumn(['organization_id', 'assigned_telegram_employee_id']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropColumn('organization_id');
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropColumn('organization_id');
        });

        Schema::table('telegram_accounts', function (Blueprint $table) {
            $table->dropForeign(['telegram_employee_id']);
            $table->dropColumn('telegram_employee_id');
        });

        Schema::dropIfExists('telegram_employees');

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropColumn('organization_id');
        });

        Schema::dropIfExists('organization_users');
        Schema::dropIfExists('organizations');
    }
};
