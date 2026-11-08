<?php

namespace Database\Factories;

use App\Models\Folder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Folder>
 */
class FolderFactory extends Factory
{
    protected $model = Folder::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'parent_id' => null,
            'name' => fake()->words(2, true),
            'category' => null,
            'color' => fake()->randomElement(['amber', 'blue', 'emerald', 'purple', 'rose', 'cyan']),
            'icon' => 'fa-solid fa-folder',
            'is_expanded' => true,
        ];
    }
}
