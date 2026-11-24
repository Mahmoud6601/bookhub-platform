<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class BookFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'author' => fake()->name(),
            'description' => fake()->paragraph(),
            'isbn' => fake()->unique()->isbn13(),
            'price' => fake()->randomFloat(2, 5, 100),
            'stock' => 10,
            'cover_url' => null,
        ];
    }
}
