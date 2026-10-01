<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class OrganizationSeatService
{
    public const DEFAULT_MAX_SEATS = 5;

    /**
     * Get the number of active web users in the organization.
     */
    public function getActiveWebUserCount(Organization $organization): int
    {
        return $organization->users()
            ->where('status', UserStatus::ACTIVE)
            ->count();
    }

    /**
     * Get the total web user count (including inactive) in the organization.
     */
    public function getTotalWebUserCount(Organization $organization): int
    {
        return $organization->users()->count();
    }

    /**
     * Get the number of available active seats for the organization.
     */
    public function getAvailableWebSeats(Organization $organization): int
    {
        $maxSeats = $organization->max_web_users ?: self::DEFAULT_MAX_SEATS;
        $activeCount = $this->getActiveWebUserCount($organization);

        return max(0, $maxSeats - $activeCount);
    }

    /**
     * Check if the organization can add a new active web user.
     */
    public function canAddWebUser(Organization $organization, UserStatus $intendedStatus = UserStatus::ACTIVE): bool
    {
        if ($intendedStatus === UserStatus::INACTIVE) {
            return true;
        }

        return $this->getAvailableWebSeats($organization) > 0;
    }

    /**
     * Assert that the organization has capacity to add a web user.
     *
     * @throws ValidationException
     */
    public function assertCanAddWebUser(Organization $organization, UserStatus $intendedStatus = UserStatus::ACTIVE): void
    {
        if (! $this->canAddWebUser($organization, $intendedStatus)) {
            $max = $organization->max_web_users ?: self::DEFAULT_MAX_SEATS;
            throw ValidationException::withMessages([
                'email' => ["The organization has reached its maximum limit of {$max} active web user seats. Please deactivate an existing web user or use Telegram-only employees."],
            ]);
        }
    }

    /**
     * Check if an inactive user can be reactivated without exceeding seat capacity.
     */
    public function canActivateWebUser(Organization $organization, User $user): bool
    {
        if ($user->status === UserStatus::ACTIVE) {
            return true; // Already active
        }

        return $this->getAvailableWebSeats($organization) > 0;
    }

    /**
     * Assert that an inactive user can be activated.
     *
     * @throws ValidationException
     */
    public function assertCanActivateWebUser(Organization $organization, User $user): void
    {
        if (! $this->canActivateWebUser($organization, $user)) {
            $max = $organization->max_web_users ?: self::DEFAULT_MAX_SEATS;
            throw ValidationException::withMessages([
                'status' => ["Cannot activate user. The organization has reached its maximum limit of {$max} active web user seats."],
            ]);
        }
    }

    /**
     * Get full seat usage metrics.
     *
     * @return array{active_seats: int, max_seats: int, available_seats: int, total_users: int, is_limit_reached: bool}
     */
    public function getSeatUsage(Organization $organization): array
    {
        $maxSeats = $organization->max_web_users ?: self::DEFAULT_MAX_SEATS;
        $activeSeats = $this->getActiveWebUserCount($organization);
        $availableSeats = max(0, $maxSeats - $activeSeats);

        return [
            'active_seats' => $activeSeats,
            'max_seats' => $maxSeats,
            'available_seats' => $availableSeats,
            'total_users' => $this->getTotalWebUserCount($organization),
            'is_limit_reached' => $availableSeats === 0,
        ];
    }
}
