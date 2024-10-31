<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class StudentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake('ar_SA')->name(),
            'phone' => fake('ar_SA')->phoneNumber(),
            'sex' => fake()->randomElement(['male', 'female']),
            'city' => 'أسفي',
        ];
    }
}
