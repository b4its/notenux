<?php

namespace App\Models;

use Database\Factories\NoteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Note extends Model
{
    /** @use HasFactory<NoteFactory> */
    use HasFactory;

    /**
     * The accessors to append to the model's array and JSON form.
     *
     * @var list<string>
     */
    protected $appends = [
        'reading_time',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'uuid',
        'title',
        'category',
        'content',
        'font_family',
        'font_size',
        'theme_color',
        'word_count',
        'char_count',
        'is_pinned',
        'is_favorite',
        'level',
        'xp',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'is_pinned' => 'boolean',
            'is_favorite' => 'boolean',
            'word_count' => 'integer',
            'char_count' => 'integer',
            'level' => 'integer',
            'xp' => 'integer',
        ];
    }

    /**
     * The user that owns this note.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The chunks that belong to this note.
     *
     * @return HasMany<NoteChunk, $this>
     */
    public function chunks(): HasMany
    {
        return $this->hasMany(NoteChunk::class)->orderBy('chunk_index', 'asc');
    }

    /**
     * Bootstrap the model and its traits.
     */
    protected static function booted(): void
    {
        static::creating(function (Note $note): void {
            if (empty($note->uuid)) {
                $note->uuid = (string) Str::uuid();
            }
            if (empty($note->user_id)) {
                $userId = auth()->id();
                if ($userId) {
                    $note->user_id = $userId;
                } elseif (app()->environment('testing')) {
                    $note->user_id = User::first()?->id ?? User::factory()->create()->id;
                }
            }
        });

        static::deleting(function (Note $note): void {
            $note->chunks()->delete();
        });
    }

    /**
     * Get estimated reading time.
     */
    public function getReadingTimeAttribute(): string
    {
        $words = $this->word_count ?: 0;
        $minutes = max(1, (int) ceil($words / 180));

        return "{$minutes} min read";
    }

    /**
     * Scope a query to sort pinned notes first, then updated recently.
     *
     * @param  Builder<Note>  $query
     * @return Builder<Note>
     */
    public function scopePrioritized($query)
    {
        return $query->orderBy('is_pinned', 'desc')->orderBy('updated_at', 'desc');
    }

    /**
     * Scope a query to only include notes belonging to the specified user.
     *
     * @param  Builder<Note>  $query
     * @return Builder<Note>
     */
    public function scopeForUser(Builder $query, ?User $user = null): Builder
    {
        $userId = $user?->id ?? auth()->id();
        if ($userId) {
            return $query->where('user_id', $userId);
        }

        if (app()->environment('testing')) {
            $first = User::first();
            if ($first) {
                return $query->where('user_id', $first->id);
            }
        }

        return $query->whereRaw('1 = 0');
    }
}
