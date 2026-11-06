<?php

namespace App\Models;

use Database\Factories\NoteChunkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NoteChunk extends Model
{
    /** @use HasFactory<NoteChunkFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'note_id',
        'chunk_uid',
        'chunk_index',
        'chunk_type',
        'content',
        'byte_size',
        'checksum',
        'metadata',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'chunk_index' => 'integer',
            'byte_size' => 'integer',
            'metadata' => 'array',
        ];
    }

    /**
     * The note this chunk belongs to.
     *
     * @return BelongsTo<Note, $this>
     */
    public function note(): BelongsTo
    {
        return $this->belongsTo(Note::class);
    }
}
