<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'role' => User::ROLE_SCHOOL_ADMIN,
            'phone' => fake()->numerify('08##########'),
            'is_active' => true,
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (): array => [
            'email_verified_at' => null,
        ]);
    }

    public function superAdmin(): static
    {
        return $this->state(fn (): array => [
            'school_id' => null,
            'role' => User::ROLE_SUPER_ADMIN,
            'is_active' => true,
        ]);
    }

    public function schoolAdmin(): static
    {
        return $this->state(fn (): array => [
            'role' => User::ROLE_SCHOOL_ADMIN,
        ]);
    }

    public function gateOfficer(): static
    {
        return $this->state(fn (): array => [
            'role' => User::ROLE_GATE_OFFICER,
        ]);
    }

    public function teacher(): static
    {
        return $this->state(fn (): array => [
            'role' => User::ROLE_TEACHER,
        ]);
    }

    public function parent(): static
    {
        return $this->state(fn (): array => [
            'role' => User::ROLE_PARENT,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}
