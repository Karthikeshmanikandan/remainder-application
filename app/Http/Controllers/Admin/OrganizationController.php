<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\OrganizationSeatService;
use App\Services\OrganizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    public function __construct(
        private OrganizationService $organizationService,
        private OrganizationSeatService $seatService
    ) {}

    public function show(): View
    {
        $this->ensureAdmin();

        $organization = auth()->user()->organization ?? Organization::first();
        if (! $organization) {
            abort(404, 'Organization not found.');
        }

        $seatUsage = $this->seatService->getSeatUsage($organization);
        $telegramEmployeesCount = $organization->telegramEmployees()->count();
        $departmentsCount = $organization->departments()->count();
        $processesCount = $organization->processes()->count();
        $projectsCount = $organization->projects()->count();

        return view('admin.organization.show', compact(
            'organization',
            'seatUsage',
            'telegramEmployeesCount',
            'departmentsCount',
            'processesCount',
            'projectsCount'
        ));
    }

    public function edit(): View
    {
        $this->ensureAdmin();

        $organization = auth()->user()->organization ?? Organization::first();
        if (! $organization) {
            abort(404, 'Organization not found.');
        }

        return view('admin.organization.edit', compact('organization'));
    }

    public function update(Request $request): RedirectResponse
    {
        $this->ensureAdmin();

        $organization = auth()->user()->organization ?? Organization::first();
        if (! $organization) {
            abort(404, 'Organization not found.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'timezone' => ['nullable', 'string', 'max:100'],
        ]);

        $this->organizationService->updateOrganization($organization, $validated);

        return redirect()->route('admin.organization.show')
            ->with('success', 'Organization settings updated successfully.');
    }

    private function ensureAdmin(): void
    {
        if (! auth()->check() || ! auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }
    }
}
