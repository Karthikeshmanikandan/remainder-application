<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmProcessExecutionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $execution = $this->route('execution');
        $user = auth()->user();

        if (! $user || ! $execution) {
            return false;
        }

        if ($execution->isCompleted()) {
            return false;
        }

        // Responsible user or Admin
        return $user->isAdmin() || $user->id === $execution->process->responsible_user_id;
    }

    public function rules(): array
    {
        return [
            'answers' => 'nullable|array',
            'answers.*.response' => 'nullable|string',
            'answers.*.notes' => 'nullable|string',
        ];
    }
}
