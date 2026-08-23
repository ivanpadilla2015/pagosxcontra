<?php

namespace Database\Factories;

use App\Models\Director;
use Illuminate\Database\Eloquent\Factories\Factory;

class DirectorFactory extends Factory
{
    protected $model = Director::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'cargo' => fake()->jobTitle(),
        ];
    }
}
