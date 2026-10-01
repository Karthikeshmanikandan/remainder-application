<?php

namespace App\Http\Requests;

use App\Enums\RecurrenceFrequency;
use App\Enums\TaskPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecurringTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // controller enforces role check
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'unique:recurring_tasks,code'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'project_id' => ['required', 'exists:projects,id'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'priority' => ['required', Rule::enum(TaskPriority::class)],
            'frequency' => ['required', Rule::enum(RecurrenceFrequency::class)],
            'interval' => ['required', 'integer', 'min:1'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'auto_create_reminder' => ['boolean'],
            'reminder_offset_minutes' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
