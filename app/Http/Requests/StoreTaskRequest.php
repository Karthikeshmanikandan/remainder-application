<?php

namespace App\Http\Requests;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreTaskRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'code' => 'required|string|max:50|unique:tasks,code',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'project_id' => 'required|exists:projects,id',
            'assigned_to' => 'nullable|exists:users,id',
            'assigned_telegram_employee_id' => 'nullable|exists:telegram_employees,id',
            'priority' => ['required', new Enum(TaskPriority::class)],
            'status' => ['required', new Enum(TaskStatus::class)],
            'due_date' => 'nullable|date',
        ];
    }
}
