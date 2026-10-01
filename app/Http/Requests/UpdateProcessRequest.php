<?php

namespace App\Http\Requests;

use App\Enums\ProcessFrequency;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateProcessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && (auth()->user()->isAdmin() || auth()->user()->isManager());
    }

    public function rules(): array
    {
        $process = $this->route('process');
        $processId = $process ? $process->id : null;

        return [
            'name' => 'required|string|max:255',
            'code' => ['required', 'string', 'max:50', Rule::unique('processes', 'code')->ignore($processId)],
            'description' => 'nullable|string',
            'responsible_user_id' => 'nullable|exists:users,id',
            'responsible_telegram_employee_id' => 'nullable|exists:telegram_employees,id',
            'frequency' => ['required', new Enum(ProcessFrequency::class)],
            'interval' => 'nullable|integer|min:1',
            'reminder_enabled' => 'nullable|boolean',
            'reminder_time' => 'nullable|string',
            'telegram_enabled' => 'nullable|boolean',
            'in_app_enabled' => 'nullable|boolean',
            'ends_at' => 'nullable|date',
            'items' => 'nullable|array',
            'items.*.is_required' => 'nullable|boolean',
            'items.*.is_enabled' => 'nullable|boolean',
            'escalation_enabled' => 'nullable|boolean',
            'escalation_rules' => 'nullable|array|max:5',
            'escalation_rules.*.level' => 'required_with:escalation_rules|integer|min:1|max:5',
            'escalation_rules.*.delay_minutes' => 'required_with:escalation_rules|integer|min:1',
            'escalation_rules.*.escalate_to_user_id' => [
                'required_with:escalation_rules',
                Rule::exists('users', 'id')->whereIn('role', [UserRole::ADMIN->value, UserRole::MANAGER->value]),
            ],
            'escalation_rules.*.is_active' => 'nullable|boolean',
        ];
    }
}
