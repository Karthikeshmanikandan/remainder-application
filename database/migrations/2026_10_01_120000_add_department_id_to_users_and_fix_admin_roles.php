<?php

use App\Enums\UserRole;
use App\Enums\UserStatus;
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
        // 1. Add department_id to users table if not present
        if (! Schema::hasColumn('users', 'department_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('department_id')->nullable()->after('organization_id')->constrained('departments')->nullOnDelete();
            });
        }

        // 2. Ensure Default Organization exists
        $defaultOrg = DB::table('organizations')->where('code', 'DEFAULT')->first();
        if (! $defaultOrg) {
            $defaultOrgId = DB::table('organizations')->insertGetId([
                'name' => 'Default Organization',
                'code' => 'DEFAULT',
                'is_active' => true,
                'max_web_users' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $defaultOrgId = $defaultOrg->id;
        }

        // 3. Fix initial bootstrap roles safely and idempotently
        $adminUser = DB::table('users')->where('email', 'admin@example.com')->first();
        if ($adminUser) {
            DB::table('users')->where('id', $adminUser->id)->update([
                'role' => UserRole::ADMIN->value,
                'status' => UserStatus::ACTIVE->value,
                'organization_id' => $adminUser->organization_id ?: $defaultOrgId,
            ]);

            DB::table('organization_users')->updateOrInsert(
                ['organization_id' => $adminUser->organization_id ?: $defaultOrgId, 'user_id' => $adminUser->id],
                ['role' => UserRole::ADMIN->value, 'status' => UserStatus::ACTIVE->value, 'updated_at' => now()]
            );
        }

        $managerUser = DB::table('users')->where('email', 'manager@example.com')->first();
        if ($managerUser) {
            DB::table('users')->where('id', $managerUser->id)->update([
                'role' => UserRole::MANAGER->value,
                'status' => UserStatus::ACTIVE->value,
                'organization_id' => $managerUser->organization_id ?: $defaultOrgId,
            ]);

            DB::table('organization_users')->updateOrInsert(
                ['organization_id' => $managerUser->organization_id ?: $defaultOrgId, 'user_id' => $managerUser->id],
                ['role' => UserRole::MANAGER->value, 'status' => UserStatus::ACTIVE->value, 'updated_at' => now()]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('users', 'department_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['department_id']);
                $table->dropColumn('department_id');
            });
        }
    }
};
