<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route-level auth middleware + controller policy enforce access
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'exists:users,id'],
            'remind_at' => ['required', 'date', 'after:now'],
        ];
    }

    public function messages(): array
    {
        return [
            'remind_at.after' => 'The reminder time must be in the future.',
        ];
    }
}
