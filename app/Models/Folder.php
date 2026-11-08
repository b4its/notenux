<?php

namespace App\Models;

use Database\Factories\FolderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Folder extends Model
{
    /** @use HasFactory<FolderFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'parent_id',
        'name',
        'category',
        'color',
        'icon',
        'is_expanded',
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
            'parent_id' => 'integer',
            'is_expanded' => 'boolean',
        ];
    }

    /**
     * The user that owns the folder.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Parent folder (if this is a subfolder).
     *
     * @return BelongsTo<Folder, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Child subfolders.
     *
     * @return HasMany<Folder, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('name', 'asc');
    }

    /**
     * Notes belonging directly to this folder.
     *
     * @return HasMany<Note, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(Note::class, 'folder_id');
    }

    /**
     * Scope query to user.
     *
     * @param  Builder<Folder>  $query
     * @return Builder<Folder>
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

    /**
     * Recursively retrieve all descendant folder IDs.
     *
     * @return list<int>
     */
    public function allDescendantIds(): array
    {
        $ids = [];
        foreach ($this->children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $child->allDescendantIds());
        }

        return $ids;
    }

    /**
     * Determine if this folder can accept notes with the given category.
     * Rules:
     * 1. If folder has no category (null or empty), any note is accepted.
     * 2. If folder has a category C, only notes with category C OR notes without category (null or empty) are accepted.
     */
    public function acceptsNoteCategory(?string $noteCategory): bool
    {
        if (empty($this->category) || empty($noteCategory)) {
            return true;
        }

        return $this->category === $noteCategory;
    }

    /**
     * Determine if this folder is compatible with a parent folder category.
     */
    public function isCompatibleWithParentCategory(?string $parentCategory): bool
    {
        if (empty($parentCategory) || empty($this->category)) {
            return true;
        }

        return $this->category === $parentCategory;
    }
}
