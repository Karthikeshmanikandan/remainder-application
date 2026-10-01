<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Department;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\OrganizationSeatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function __construct(
        private OrganizationSeatService $seatService,
        private AuditLogService $auditLogService
    ) {}

    public function index(Request $request)
    {
        $org = auth()->user()->organization ?? Organization::first();
        $query = User::with(['department', 'telegramAccount']);

        if ($org) {
            $query->where('organization_id', $org->id);
        }

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->latest()->paginate(15)->withQueryString();
        $departments = $org ? Department::where('organization_id', $org->id)->where('is_active', true)->orderBy('name')->get() : collect();

        $seatUsage = $org ? $this->seatService->getSeatUsage($org) : [
            'active_seats' => $users->total(),
            'max_seats' => 5,
            'available_seats' => 0,
            'is_limit_reached' => false,
        ];

        return view('users.index', compact('users', 'departments', 'seatUsage', 'org'));
    }

    public function create()
    {
        $org = auth()->user()->organization ?? Organization::first();
        $seatUsage = $org ? $this->seatService->getSeatUsage($org) : null;
        $departments = $org ? Department::where('organization_id', $org->id)->where('is_active', true)->orderBy('name')->get() : collect();

        return view('users.create', compact('seatUsage', 'departments', 'org'));
    }

    public function store(Request $request)
    {
        $org = auth()->user()->organization ?? Organization::first();
        $status = UserStatus::from($request->input('status', 'active'));

        if ($org) {
            $this->seatService->assertCanAddWebUser($org, $status);
        }

        $orgId = $org?->id ?? 1;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => ['required', Rule::enum(UserRole::class)],
            'status' => ['required', Rule::enum(UserStatus::class)],
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where('organization_id', $orgId)],
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $validated['organization_id'] = $orgId;

        $user = DB::transaction(function () use ($validated, $org) {
            $user = User::create($validated);

            if ($org) {
                OrganizationUser::create([
                    'organization_id' => $org->id,
                    'user_id' => $user->id,
                    'role' => $user->role,
                    'status' => $user->status,
                ]);
            }

            $this->auditLogService->log(
                action: AuditAction::CREATED,
                auditable: $user,
                beforeValues: null,
                afterValues: [
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role->value,
                    'status' => $user->status->value,
                    'department_id' => $user->department_id,
                ],
                summary: "Web User '{$user->name}' ({$user->email}) created."
            );

            return $user;
        });

        return redirect()->route('users.index')->with('success', "Web User '{$user->name}' created successfully.");
    }

    public function edit(User $user)
    {
        $this->ensureSameOrganization($user);

        $org = auth()->user()->organization ?? $user->organization;
        $departments = $org ? Department::where('organization_id', $org->id)->where('is_active', true)->orderBy('name')->get() : collect();

        return view('users.edit', compact('user', 'departments'));
    }

    public function update(Request $request, User $user)
    {
        $this->ensureSameOrganization($user);

        $org = auth()->user()->organization ?? $user->organization;
        $newStatus = UserStatus::from($request->input('status', $user->status->value));
        $newRole = UserRole::from($request->input('role', $user->role->value));
        $orgId = $org?->id ?? 1;

        // Admin self-protection & last administrator protection
        if ($user->id === auth()->id() && $newStatus === UserStatus::INACTIVE) {
            throw ValidationException::withMessages([
                'status' => ['You cannot deactivate your own administrative account.'],
            ]);
        }

        if ($user->role === UserRole::ADMIN && ($newRole !== UserRole::ADMIN || $newStatus === UserStatus::INACTIVE)) {
            $otherAdminsCount = User::where('organization_id', $orgId)
                ->where('role', UserRole::ADMIN)
                ->where('status', UserStatus::ACTIVE)
                ->where('id', '!=', $user->id)
                ->count();

            if ($otherAdminsCount === 0) {
                throw ValidationException::withMessages([
                    'role' => ['Cannot demote or deactivate the last remaining active Administrator in this organization.'],
                ]);
            }
        }

        if ($org && $user->status !== UserStatus::ACTIVE && $newStatus === UserStatus::ACTIVE) {
            $this->seatService->assertCanActivateWebUser($org, $user);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role' => ['required', Rule::enum(UserRole::class)],
            'status' => ['required', Rule::enum(UserStatus::class)],
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where('organization_id', $orgId)],
        ]);

        if ($request->filled('password')) {
            $request->validate(['password' => 'string|min:8|confirmed']);
            $validated['password'] = Hash::make($request->password);
        }

        DB::transaction(function () use ($user, $validated, $org) {
            $before = $user->toArray();
            $user->update($validated);

            if ($org) {
                OrganizationUser::updateOrCreate(
                    ['organization_id' => $org->id, 'user_id' => $user->id],
                    ['role' => $user->role, 'status' => $user->status]
                );
            }

            $this->auditLogService->log(
                action: AuditAction::UPDATED,
                auditable: $user,
                beforeValues: $before,
                afterValues: $user->fresh()->toArray(),
                summary: "Web User '{$user->name}' updated."
            );
        });

        return redirect()->route('users.index')->with('success', "Web User '{$user->name}' updated successfully.");
    }

    private function ensureSameOrganization(User $targetUser): void
    {
        if (auth()->check() && auth()->user()->organization_id && $targetUser->organization_id) {
            if (auth()->user()->organization_id !== $targetUser->organization_id) {
                abort(403, 'Unauthorized action for this organization.');
            }
        }
    }
}
