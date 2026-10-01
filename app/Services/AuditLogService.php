<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\TelegramEmployee;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

class AuditLogService
{
    /**
     * Sensitive fields that must NEVER be persisted in audit logs.
     *
     * @var array<int, string>
     */
    protected array $sensitiveKeys = [
        'password',
        'password_confirmation',
        'remember_token',
        'api_token',
        'telegram_bot_token',
        'secret',
        'webhook_secret',
        'credentials',
        'bot_token',
        'token',
        'auth_key',
        'authorization',
        'session_id',
        'session_token',
        'session',
        'cookie',
        'access_token',
        'refresh_token',
    ];

    /**
     * Record a new audit log entry.
     *
     * @param  array<string, mixed>|null  $beforeValues
     * @param  array<string, mixed>|null  $afterValues
     * @param  array<string, mixed>|null  $metadata
     */
    public function log(
        AuditAction|string $action,
        Model $auditable,
        ?array $beforeValues = null,
        ?array $afterValues = null,
        ?string $summary = null,
        ?array $metadata = null,
        User|TelegramEmployee|null $actor = null
    ): AuditLog {
        $actionEnum = $action instanceof AuditAction ? $action : AuditAction::from($action);

        $actorModel = $actor ?? auth()->user();
        $userId = ($actorModel instanceof User) ? $actorModel->id : null;
        $actorType = $actorModel ? $actorModel->getMorphClass() : null;
        $actorId = $actorModel ? $actorModel->getKey() : null;

        $orgId = $auditable->organization_id ?? $actorModel?->organization_id ?? auth()->user()?->organization_id ?? 1;

        $sanitizedBefore = $beforeValues !== null ? $this->sanitize($beforeValues) : null;
        $sanitizedAfter = $afterValues !== null ? $this->sanitize($afterValues) : null;
        $sanitizedMetadata = $metadata !== null ? $this->sanitize($metadata) : null;

        if ($summary === null) {
            $summary = $this->generateFactualSummary($actionEnum, $auditable, $sanitizedBefore, $sanitizedAfter);
        }

        $ipAddress = request()?->ip();
        $userAgent = request()?->userAgent();

        return AuditLog::create([
            'organization_id' => $orgId,
            'user_id' => $userId,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'action' => $actionEnum,
            'auditable_type' => $auditable->getMorphClass(),
            'auditable_id' => $auditable->getKey(),
            'summary' => $summary,
            'before_values' => $sanitizedBefore,
            'after_values' => $sanitizedAfter,
            'metadata' => $sanitizedMetadata,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }

    /**
     * Recursively sanitize sensitive keys from arrays or objects.
     */
    public function sanitize(mixed $data): mixed
    {
        if (! is_array($data)) {
            return $data;
        }

        $cleaned = [];

        foreach ($data as $key => $value) {
            $keyStr = strtolower((string) $key);

            // Check if key matches any sensitive pattern
            $isSensitive = false;
            foreach ($this->sensitiveKeys as $sensitive) {
                if (str_contains($keyStr, $sensitive)) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                continue; // Omit sensitive field entirely
            }

            if (is_array($value)) {
                $cleaned[$key] = $this->sanitize($value);
            } else {
                $cleaned[$key] = $value;
            }
        }

        return $cleaned;
    }

    /**
     * Generate neutral, factual summary based on changed data.
     */
    protected function generateFactualSummary(AuditAction $action, Model $auditable, ?array $before, ?array $after): string
    {
        $entityName = class_basename($auditable);

        return match ($action) {
            AuditAction::CREATED => "{$entityName} created.",
            AuditAction::UPDATED => "{$entityName} updated.",
            AuditAction::DELETED => "{$entityName} deleted.",
            AuditAction::ACTIVATED => "{$entityName} activated.",
            AuditAction::DEACTIVATED => "{$entityName} deactivated.",
            AuditAction::PAUSED => "{$entityName} paused.",
            AuditAction::RESUMED => "{$entityName} resumed.",
            AuditAction::CANCELLED => "{$entityName} cancelled.",
            AuditAction::ASSIGNED => "{$entityName} assigned.",
            AuditAction::REASSIGNED => "{$entityName} responsible user reassigned.",
            AuditAction::COMPLETED => "{$entityName} completed.",
            AuditAction::ACKNOWLEDGED => "{$entityName} acknowledged.",
            AuditAction::CONFIGURED => "{$entityName} configuration updated.",
        };
    }

    /**
     * Get paginated audit logs for a specific model instance.
     */
    public function getLogsFor(Model $auditable, int $perPage = 15): LengthAwarePaginator
    {
        return AuditLog::query()
            ->forAuditable($auditable)
            ->with(['user'])
            ->latest('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Get filtered audit logs with pagination.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getFilteredLogs(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = AuditLog::with(['user', 'actor', 'auditable']);

        if (! empty($filters['organization_id'])) {
            $query->where('organization_id', $filters['organization_id']);
        }

        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (! empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (! empty($filters['auditable_type'])) {
            $type = $filters['auditable_type'];
            if (! str_contains($type, '\\')) {
                $type = "App\\Models\\{$type}";
            }
            $query->where('auditable_type', $type);
        }

        if (! empty($filters['auditable_id'])) {
            $query->where('auditable_id', $filters['auditable_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['date_from'])->startOfDay());
        }

        if (! empty($filters['date_to'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['date_to'])->endOfDay());
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('summary', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        return $query->latest('created_at')->paginate($perPage)->withQueryString();
    }
}
