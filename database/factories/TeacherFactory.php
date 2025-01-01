<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class TeacherFactory extends Factory
{
    public function definition(): array
    {
        $sex = fake()->randomElement(['male', 'female']);

        return [
            'name' => fake('ar_SA')->name($sex),
            'phone' => fake('ar_SA')->phoneNumber(),
            'sex' => $sex,
        ];
    }
}
