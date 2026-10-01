<?php

namespace Database\Factories;

use App\Enums\RecurrenceFrequency;
use App\Enums\RecurringTaskStatus;
use App\Enums\TaskPriority;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RecurringTaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => 'REC-'.$this->faker->unique()->numberBetween(1000, 9999),
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(),
            'project_id' => Project::factory(),
            'assigned_to' => User::factory(),
            'created_by' => User::factory(),
            'priority' => TaskPriority::MEDIUM,
            'frequency' => RecurrenceFrequency::DAILY,
            'interval' => 1,
            'starts_at' => now(),
            'ends_at' => null,
            'next_run_at' => now(),
            'last_run_at' => null,
            'status' => RecurringTaskStatus::ACTIVE,
            'auto_create_reminder' => false,
            'reminder_offset_minutes' => 60,
        ];
    }
}
