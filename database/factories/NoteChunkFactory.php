<?php

namespace Database\Factories;

use App\Models\Note;
use App\Models\NoteChunk;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NoteChunk>
 */
class NoteChunkFactory extends Factory
{
    protected $model = NoteChunk::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $content = fake()->paragraph();

        return [
            'note_id' => Note::factory(),
            'chunk_uid' => 'chk_'.(string) Str::ulid(),
            'chunk_index' => 0,
            'chunk_type' => fake()->randomElement(['heading', 'paragraph', 'code', 'list']),
            'content' => $content,
            'byte_size' => strlen($content),
            'checksum' => hash('sha256', $content),
            'metadata' => [
                'generated' => true,
            ],
        ];
    }
}
