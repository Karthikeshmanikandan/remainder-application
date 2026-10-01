@if($errors->any())
    <div class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-800 border border-red-200">
        <p class="font-semibold mb-1">Please correct the following errors:</p>
        <ul class="list-disc list-inside space-y-1">
            @foreach($errors->all() as $error) 
                <li>{{ $error }}</li> 
            @endforeach
        </ul>
    </div>
@endif

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1">Full Name</label>
        <input type="text" name="name" required value="{{ old('name', $user->name ?? '') }}" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
    </div>

    <div>
        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1">Email Address (Login)</label>
        <input type="email" name="email" required value="{{ old('email', $user->email ?? '') }}" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
    </div>

    <div>
        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1">Role</label>
        <select name="role" required class="w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
            @foreach(\App\Enums\UserRole::cases() as $role)
                <option value="{{ $role->value }}" {{ old('role', $user->role->value ?? '') === $role->value ? 'selected' : '' }}>
                    {{ ucfirst($role->value) }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1">Status</label>
        <select name="status" required class="w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
            @foreach(\App\Enums\UserStatus::cases() as $status)
                <option value="{{ $status->value }}" {{ old('status', $user->status->value ?? '') === $status->value ? 'selected' : '' }}>
                    {{ ucfirst($status->value) }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="sm:col-span-2">
        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1">Department (Optional)</label>
        <select name="department_id" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
            <option value="">-- No Department Assigned --</option>
            @if(isset($departments))
                @foreach($departments as $dept)
                    <option value="{{ $dept->id }}" {{ (string) old('department_id', $user->department_id ?? '') === (string) $dept->id ? 'selected' : '' }}>
                        {{ $dept->name }} {{ $dept->code ? '(' . $dept->code . ')' : '' }}
                    </option>
                @endforeach
            @endif
        </select>
    </div>

    <div class="sm:col-span-2 pt-4 border-t border-slate-200 mt-2">
        <h4 class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-2">Password Configuration</h4>
    </div>

    <div>
        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1">
            Password {{ isset($user) ? '(leave blank to keep current)' : '' }}
        </label>
        <input type="password" name="password" {{ isset($user) ? '' : 'required' }} minlength="8" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
    </div>

    <div>
        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1">Confirm Password</label>
        <input type="password" name="password_confirmation" {{ isset($user) ? '' : 'required' }} minlength="8" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
    </div>
</div>