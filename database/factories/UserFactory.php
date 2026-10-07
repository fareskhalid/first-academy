<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return ['name' => fake()->name(), 'phone' => '+2010'.fake()->unique()->numerify('########'), 'role' => 'student', 'status' => 'active', 'locale' => 'en', 'password' => Hash::make('TestPassword123!')];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (User $user) {
            if (! $user->isInstructor()) {
                $user->update(['student_code' => 'STU-'.str_pad((string) $user->id, 6, '0', STR_PAD_LEFT)]);
                $user->profile()->create(['whatsapp_phone' => $user->phone, 'whatsapp_declared_at' => now()]);
            }
        });
    }

    public function instructor(): static
    {
        return $this->state(fn () => ['role' => 'instructor']);
    }
}
