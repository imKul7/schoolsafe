<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<School>
 */
class SchoolFactory extends Factory
{
    protected $model = School::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $identifier = fake()->unique()->numerify('########');

        return [
            'code' => "SCH-{$identifier}",
            'name' => fake()->company().' School',
            'npsn' => null,
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('08##########'),
            'address' => fake()->address(),
            'city' => fake()->city(),
            'province' => fake()->state(),
            'logo_path' => null,
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}
