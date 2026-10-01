<?php

namespace Database\Seeders;

use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::firstOrCreate(['code' => 'DEFAULT'], [
            'name' => 'Default Organization',
            'max_web_users' => 5,
            'is_active' => true,
        ]);

        $admin = User::updateOrCreate(['email' => 'admin@example.com'], [
            'name' => 'Admin User',
            'password' => Hash::make('Password123!'),
            'role' => UserRole::ADMIN,
            'status' => UserStatus::ACTIVE,
            'organization_id' => $org->id,
        ]);

        OrganizationUser::updateOrCreate(
            ['organization_id' => $org->id, 'user_id' => $admin->id],
            ['role' => UserRole::ADMIN->value, 'status' => UserStatus::ACTIVE->value]
        );

        $manager = User::updateOrCreate(['email' => 'manager@example.com'], [
            'name' => 'Manager User',
            'password' => Hash::make('Password123!'),
            'role' => UserRole::MANAGER,
            'status' => UserStatus::ACTIVE,
            'organization_id' => $org->id,
        ]);

        OrganizationUser::updateOrCreate(
            ['organization_id' => $org->id, 'user_id' => $manager->id],
            ['role' => UserRole::MANAGER->value, 'status' => UserStatus::ACTIVE->value]
        );

        $employee = User::updateOrCreate(['email' => 'employee@example.com'], [
            'name' => 'Employee User',
            'password' => Hash::make('Password123!'),
            'role' => UserRole::EMPLOYEE,
            'status' => UserStatus::ACTIVE,
            'organization_id' => $org->id,
        ]);

        OrganizationUser::updateOrCreate(
            ['organization_id' => $org->id, 'user_id' => $employee->id],
            ['role' => UserRole::EMPLOYEE->value, 'status' => UserStatus::ACTIVE->value]
        );

        $project = Project::firstOrCreate(['code' => 'PRJ-001'], [
            'name' => 'Alpha Development',
            'description' => 'Development of the new Alpha product.',
            'status' => ProjectStatus::ACTIVE,
            'created_by' => $admin->id,
            'start_date' => now(),
            'end_date' => now()->addMonths(3),
        ]);

        Task::firstOrCreate(['code' => 'TSK-001'], [
            'title' => 'Setup environment',
            'description' => 'Setup local dev environment',
            'project_id' => $project->id,
            'assigned_to' => $employee->id,
            'created_by' => $manager->id,
            'priority' => TaskPriority::HIGH,
            'status' => TaskStatus::IN_PROGRESS,
            'due_date' => now()->addDays(2),
        ]);

        $this->call(DepartmentAndProcessTemplateSeeder::class);
    }
}
