<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);
        $icons = ['fa-solid fa-flask', 'fa-solid fa-dragon', 'fa-solid fa-skull', 'fa-solid fa-shield-halved', 'fa-solid fa-gem', 'fa-solid fa-fire', 'fa-solid fa-crown', 'fa-solid fa-ghost'];
        $colors = ['cyan', 'blue', 'emerald', 'amber', 'rose', 'purple'];

        return [
            'user_id' => User::factory(),
            'name' => ucwords($name),
            'slug' => Str::slug($name),
            'icon' => fake()->randomElement($icons),
            'color' => fake()->randomElement($colors),
            'description' => fake()->sentence(),
            'is_system' => false,
        ];
    }

    /**
     * Indicate that the category is a system default.
     */
    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
            'is_system' => true,
        ]);
    }
}
