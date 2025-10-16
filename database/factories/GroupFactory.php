<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class GroupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'مجموعة '.fake('ar_SA')->unique()->numberBetween(1, 100),
            'type' => fake()->randomElement(['half_page', 'two_lines']),
        ];
    }
}
