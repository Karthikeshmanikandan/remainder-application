<?php

namespace App\Http\Requests;

use App\Enums\ProcessFrequency;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreProcessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && (auth()->user()->isAdmin() || auth()->user()->isManager());
    }

    public function rules(): array
    {
        return [
            'department_id' => 'required|exists:departments,id',
            'process_template_id' => 'nullable|exists:process_templates,id',
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50|unique:processes,code',
            'description' => 'nullable|string',
            'responsible_user_id' => 'nullable|exists:users,id',
            'responsible_telegram_employee_id' => 'nullable|exists:telegram_employees,id',
            'frequency' => ['required', new Enum(ProcessFrequency::class)],
            'interval' => 'nullable|integer|min:1',
            'reminder_enabled' => 'nullable|boolean',
            'reminder_time' => 'nullable|string',
            'telegram_enabled' => 'nullable|boolean',
            'in_app_enabled' => 'nullable|boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'items' => 'nullable|array',
            'items.*.template_item_id' => 'nullable|exists:process_template_items,id',
            'items.*.question' => 'required_with:items|string',
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
