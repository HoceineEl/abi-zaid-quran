<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class MemoGroupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'مجموعة '.fake('ar_SA')->unique()->numberBetween(1, 100),
            'price' => fake()->randomElement([50, 75, 100, 150]),
        ];
    }
}
