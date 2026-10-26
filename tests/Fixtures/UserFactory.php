<?php

namespace Jurager\Teams\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\Factory;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return ['email' => fake()->unique()->safeEmail()];
    }
}
