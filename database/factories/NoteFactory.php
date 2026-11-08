<?php

namespace Database\Factories;

use App\Models\Note;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Note>
 */
class NoteFactory extends Factory
{
    protected $model = Note::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $categories = ['catatan', 'tugas', 'quest', 'lore', 'spellbook', 'strategy', 'journal'];
        $fonts = ['pixel', 'sans', 'mono', 'serif'];
        $sizes = ['14px', '16px', '18px', '20px'];
        $title = fake()->catchPhrase();
        $paragraphs = fake()->paragraphs(3, true);

        return [
            'user_id' => auth()->id() ?? User::first()?->id ?? User::factory(),
            'uuid' => (string) Str::uuid(),
            'title' => $title,
            'category' => fake()->randomElement($categories),
            'content' => '<p>'.str_replace("\n\n", '</p><p>', $paragraphs).'</p>',
            'font_family' => fake()->randomElement($fonts),
            'font_size' => fake()->randomElement($sizes),
            'theme_color' => 'blue',
            'word_count' => str_word_count($paragraphs),
            'char_count' => mb_strlen($paragraphs),
            'is_pinned' => fake()->boolean(20),
            'is_favorite' => fake()->boolean(30),
            'level' => fake()->numberBetween(1, 15),
            'xp' => fake()->numberBetween(50, 1500),
        ];
    }

    /**
     * Indicate that the note is pinned.
     */
    public function pinned(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_pinned' => true,
        ]);
    }

    /**
     * Indicate that the note belongs to the specified user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }
}
