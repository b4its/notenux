<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'icon',
        'color',
        'description',
        'is_system',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    /**
     * Bootstrap the model and its traits.
     */
    protected static function booted(): void
    {
        static::creating(function (Category $category): void {
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });
    }

    /**
     * The user that owns the category.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Notes that belong to this category by slug.
     *
     * @return HasMany<Note, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(Note::class, 'category', 'slug');
    }

    /**
     * Scope a query to include system categories and categories belonging to the user.
     *
     * @param  Builder<Category>  $query
     * @return Builder<Category>
     */
    public function scopeForUser(Builder $query, ?User $user = null): Builder
    {
        $userId = $user?->id;

        return $query->where(function (Builder $q) use ($userId): void {
            $q->where('is_system', true);
            if ($userId) {
                $q->orWhere('user_id', $userId);
            }
        });
    }

    /**
     * List of core system categories.
     *
     * @return list<array<string, mixed>>
     */
    public static function defaultCategories(): array
    {
        return [
            [
                'name' => 'Catatan',
                'slug' => 'catatan',
                'icon' => 'fa-solid fa-note-sticky',
                'color' => 'blue',
                'description' => 'Kategori utama untuk catatan harian dan pemikiran.',
                'is_system' => true,
            ],
            [
                'name' => 'Tugas',
                'slug' => 'tugas',
                'icon' => 'fa-solid fa-list-check',
                'color' => 'emerald',
                'description' => 'Daftar tugas, agenda, dan to-do list penting.',
                'is_system' => true,
            ],
            [
                'name' => 'Quests',
                'slug' => 'quest',
                'icon' => 'fa-solid fa-khanda',
                'color' => 'amber',
                'description' => 'Active quests, missions, and adventure chronicles.',
                'is_system' => true,
            ],
            [
                'name' => 'Spellbooks',
                'slug' => 'spellbook',
                'icon' => 'fa-solid fa-wand-magic-sparkles',
                'color' => 'purple',
                'description' => 'Incantations, magical scripts, and arcane formulae.',
                'is_system' => true,
            ],
            [
                'name' => 'Boss Raids',
                'slug' => 'strategy',
                'icon' => 'fa-solid fa-shield-halved',
                'color' => 'rose',
                'description' => 'Tactical raid strategies and boss battle tactics.',
                'is_system' => true,
            ],
            [
                'name' => 'Ancient Lore',
                'slug' => 'lore',
                'icon' => 'fa-solid fa-scroll',
                'color' => 'cyan',
                'description' => 'World history, legends, and forbidden knowledge.',
                'is_system' => true,
            ],
            [
                'name' => 'Library Vault',
                'slug' => 'archive',
                'icon' => 'fa-solid fa-box-archive',
                'color' => 'amber',
                'description' => 'Archived scrolls preserved in the grand vault.',
                'is_system' => true,
            ],
            [
                'name' => 'General Notes',
                'slug' => 'general',
                'icon' => 'fa-solid fa-book-journal-whills',
                'color' => 'gray',
                'description' => 'Everyday scribbles and miscellaneous thoughts.',
                'is_system' => true,
            ],
        ];
    }
}
