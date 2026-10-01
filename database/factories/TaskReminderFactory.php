<?php

namespace Database\Factories;

use App\Enums\ReminderStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskReminderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'user_id' => User::factory(),
            'remind_at' => now()->addHour(),
            'status' => ReminderStatus::PENDING,
        ];
    }

    public function due(): static
    {
        return $this->state(['remind_at' => now()->subMinute()]);
    }

    public function triggered(): static
    {
        return $this->state([
            'status' => ReminderStatus::TRIGGERED,
            'triggered_at' => now()->subMinute(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => ReminderStatus::CANCELLED]);
    }
}
