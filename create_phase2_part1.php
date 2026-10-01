<?php

function create_file($path, $content)
{
    $dir = dirname(__DIR__.'/'.$path);
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents(__DIR__.'/'.$path, $content);
    echo "Created: $path\n";
}

// ENUMS
create_file('app/Enums/UserRole.php', <<<'PHP'
<?php
namespace App\Enums;

enum UserRole: string
{
    case ADMIN = 'admin';
    case MANAGER = 'manager';
    case EMPLOYEE = 'employee';
}
PHP);

create_file('app/Enums/UserStatus.php', <<<'PHP'
<?php
namespace App\Enums;

enum UserStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}
PHP);

// MIGRATION FOR USERS TABLE MODIFICATION
$migrationName = date('Y_m_d_His').'_add_role_and_status_to_users_table.php';
create_file('database/migrations/'.$migrationName, <<<'PHP'
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('employee')->after('password');
            $table->string('status')->default('active')->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'status']);
        });
    }
};
PHP);

// UPDATE USER MODEL
create_file('app/Models/User.php', <<<'PHP'
<?php
namespace App\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'role' => UserRole::class,
        'status' => UserStatus::class,
    ];

    public function assignedTasks()
    {
        return $this->hasMany(Task::class, 'assigned_to');
    }

    public function createdTasks()
    {
        return $this->hasMany(Task::class, 'created_by');
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::ADMIN;
    }

    public function isManager(): bool
    {
        return $this->role === UserRole::MANAGER;
    }
}
PHP);

// UPDATE SEEDER
create_file('database/seeders/DatabaseSeeder.php', <<<'PHP'
<?php
namespace Database\Seeders;
use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Enums\TaskPriority;
use App\Enums\UserRole;
use App\Enums\UserStatus;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(['email' => 'admin@example.com'], [
            'name' => 'Admin User',
            'password' => Hash::make('Password123!'),
            'role' => UserRole::ADMIN,
            'status' => UserStatus::ACTIVE,
        ]);

        $manager = User::firstOrCreate(['email' => 'manager@example.com'], [
            'name' => 'Manager User',
            'password' => Hash::make('Password123!'),
            'role' => UserRole::MANAGER,
            'status' => UserStatus::ACTIVE,
        ]);

        $employee = User::firstOrCreate(['email' => 'employee@example.com'], [
            'name' => 'Employee User',
            'password' => Hash::make('Password123!'),
            'role' => UserRole::EMPLOYEE,
            'status' => UserStatus::ACTIVE,
        ]);

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
    }
}
PHP);
