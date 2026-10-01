<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->bothify('PRJ-###'),
            'name' => $this->faker->sentence(3),
            'status' => ProjectStatus::DRAFT,
        ];
    }
}
