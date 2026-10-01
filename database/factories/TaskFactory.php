<?php

namespace Database\Factories;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->bothify('TSK-###'),
            'title' => $this->faker->sentence(4),
            'project_id' => Project::factory(),
            'status' => TaskStatus::PENDING,
            'priority' => TaskPriority::MEDIUM,
        ];
    }
}
