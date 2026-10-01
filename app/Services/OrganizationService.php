<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;

class OrganizationService
{
    public function __construct(
        private AuditLogService $auditLogService
    ) {}

    /**
     * Create a new organization.
     *
     * @param  array{name: string, code?: string, is_active?: bool, max_web_users?: int}  $data
     */
    public function createOrganization(array $data): Organization
    {
        return DB::transaction(function () use ($data) {
            $code = ! empty($data['code']) ? strtoupper(trim($data['code'])) : $this->generateUniqueCode($data['name']);

            $organization = Organization::create([
                'name' => trim($data['name']),
                'code' => $code,
                'is_active' => (bool) ($data['is_active'] ?? true),
                'max_web_users' => isset($data['max_web_users']) ? (int) $data['max_web_users'] : 5,
            ]);

            $this->auditLogService->log(
                action: AuditAction::CREATED,
                auditable: $organization,
                beforeValues: null,
                afterValues: $organization->toArray(),
                summary: "Organization '{$organization->name}' ({$organization->code}) created."
            );

            return $organization;
        });
    }

    /**
     * Update an organization.
     *
     * @param  array{name?: string, is_active?: bool, max_web_users?: int}  $data
     */
    public function updateOrganization(Organization $organization, array $data): Organization
    {
        return DB::transaction(function () use ($organization, $data) {
            $before = $organization->toArray();

            $organization->update([
                'name' => isset($data['name']) ? trim($data['name']) : $organization->name,
                'contact_email' => array_key_exists('contact_email', $data) ? $data['contact_email'] : $organization->contact_email,
                'contact_phone' => array_key_exists('contact_phone', $data) ? $data['contact_phone'] : $organization->contact_phone,
                'timezone' => array_key_exists('timezone', $data) ? $data['timezone'] : $organization->timezone,
                'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : $organization->is_active,
                'max_web_users' => isset($data['max_web_users']) ? (int) $data['max_web_users'] : $organization->max_web_users,
            ]);

            $this->auditLogService->log(
                action: AuditAction::UPDATED,
                auditable: $organization,
                beforeValues: $before,
                afterValues: $organization->fresh()->toArray(),
                summary: "Organization '{$organization->name}' updated."
            );

            return $organization;
        });
    }

    /**
     * Generate unique organization code.
     */
    public function generateUniqueCode(string $name): string
    {
        $base = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 4));
        if (strlen($base) < 3) {
            $base = 'ORG';
        }

        $code = $base;
        $counter = 1;
        while (Organization::where('code', $code)->exists()) {
            $code = "{$base}-".str_pad((string) $counter, 3, '0', STR_PAD_LEFT);
            $counter++;
        }

        return $code;
    }
}
