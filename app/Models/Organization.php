<?php

namespace App\Models;

use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'contact_email',
        'contact_phone',
        'timezone',
        'is_active',
        'max_web_users',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'max_web_users' => 'integer',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function organizationUsers(): HasMany
    {
        return $this->hasMany(OrganizationUser::class);
    }

    public function telegramEmployees(): HasMany
    {
        return $this->hasMany(TelegramEmployee::class);
    }

    public function activeTelegramEmployees(): HasMany
    {
        return $this->hasMany(TelegramEmployee::class)->where('is_active', true);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function recurringTasks(): HasMany
    {
        return $this->hasMany(RecurringTask::class);
    }

    public function processTemplates(): HasMany
    {
        return $this->hasMany(ProcessTemplate::class);
    }

    public function processes(): HasMany
    {
        return $this->hasMany(Process::class);
    }

    public function processExecutions(): HasMany
    {
        return $this->hasMany(ProcessExecution::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function activeWebUsersCount(): int
    {
        return $this->users()->where('status', UserStatus::ACTIVE)->count();
    }

    public function availableWebSeats(): int
    {
        return max(0, $this->max_web_users - $this->activeWebUsersCount());
    }

    public function canAddWebUser(): bool
    {
        return $this->activeWebUsersCount() < $this->max_web_users;
    }
}
