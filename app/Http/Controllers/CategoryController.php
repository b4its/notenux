<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Note;
use App\Models\NoteChunk;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CategoryController extends Controller
{
    /**
     * Display a listing of categories (both system and personalized).
     */
    public function index(Request $request): View|JsonResponse
    {
        $user = $this->resolveUser($request);
        $categories = Category::forUser($user)
            ->withCount(['notes' => fn (Builder $q) => $q->where('user_id', $user->id)])
            ->orderBy('is_system', 'desc')
            ->orderBy('name', 'asc')
            ->get();

        if ($categories->isEmpty()) {
            foreach (Category::defaultCategories() as $def) {
                Category::firstOrCreate(['slug' => $def['slug']], $def);
            }
            $categories = Category::forUser($user)
                ->withCount(['notes' => fn (Builder $q) => $q->where('user_id', $user->id)])
                ->orderBy('is_system', 'desc')
                ->orderBy('name', 'asc')
                ->get();
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'data' => $categories,
            ]);
        }

        $stats = $this->calculatePlayerStats($user);

        return view('categories.index', [
            'categories' => $categories,
            'user' => $user,
            'stats' => $stats,
        ]);
    }

    /**
     * Store a newly created personalized category.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $user = $this->resolveUser($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:64'],
            'slug' => ['nullable', 'string', 'max:64', 'regex:/^[a-zA-Z0-9_-]+$/'],
            'icon' => ['nullable', 'string', 'max:64', 'regex:/\Afa-(?:solid|regular|brands) fa-[a-z0-9-]+\z/'],
            'color' => ['nullable', 'string', 'in:cyan,blue,emerald,amber,rose,purple,gray'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $category = $this->mutateCategories(fn (): Category => Category::create([
            'user_id' => $user->id,
            'name' => trim($validated['name']),
            'slug' => $this->allocateSlug($validated['slug'] ?? $validated['name']),
            'icon' => $validated['icon'] ?? 'fa-solid fa-folder',
            'color' => $validated['color'] ?? 'cyan',
            'description' => $validated['description'] ?? null,
            'is_system' => false,
        ]));

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Category forged successfully!',
                'data' => $category,
            ], 201);
        }

        return redirect()->route('categories.index')
            ->with('status', 'success')
            ->with('message', "Category '{$category->name}' forged successfully!");
    }

    /**
     * Update the specified personalized category.
     */
    public function update(Request $request, Category $category): RedirectResponse|JsonResponse
    {
        $user = $this->resolveUser($request);

        if ($category->user_id !== null && $category->user_id !== $user->id) {
            abort(403, 'Unauthorized access to category.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:64'],
            'slug' => ['nullable', 'string', 'max:64', 'regex:/^[a-zA-Z0-9_-]+$/'],
            'icon' => ['nullable', 'string', 'max:64', 'regex:/\Afa-(?:solid|regular|brands) fa-[a-z0-9-]+\z/'],
            'color' => ['nullable', 'string', 'in:cyan,blue,emerald,amber,rose,purple,gray'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $this->mutateCategories(function () use ($category, $validated, $user): void {
            $category->refresh();
            $oldSlug = $category->slug;
            $newSlug = isset($validated['slug']) ? $this->allocateSlug($validated['slug'], $category->id) : $oldSlug;

            if ($newSlug !== $oldSlug) {
                // Safely cascade slug change to all existing notes belonging to this user
                Note::where('category', $oldSlug)->where('user_id', $user->id)->update(['category' => $newSlug]);
            }

            $category->update([
                'name' => trim($validated['name']),
                'slug' => $newSlug,
                'icon' => $validated['icon'] ?? $category->icon,
                'color' => $validated['color'] ?? $category->color,
                'description' => array_key_exists('description', $validated) ? $validated['description'] : $category->description,
            ]);
        });

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Category updated successfully!',
                'data' => $category,
            ]);
        }

        return redirect()->route('categories.index')
            ->with('status', 'success')
            ->with('message', "Category '{$category->name}' updated!");
    }

    /**
     * Remove the specified category and automatically delete its associated notes atomically.
     */
    public function destroy(Request $request, Category $category): RedirectResponse|JsonResponse
    {
        $user = $this->resolveUser($request);

        if ($category->user_id !== null && $category->user_id !== $user->id) {
            abort(403, 'Unauthorized access to category.');
        }

        [$name, $slug, $deletedNotesCount] = $this->mutateCategories(function () use ($category, $user): array {
            $category->refresh();
            $name = $category->name;
            $slug = $category->slug;
            $deletedCount = $category->notes()->where('user_id', $user->id)->count();

            $category->delete();

            return [$name, $slug, $deletedCount];
        });

        if ($request->wantsJson() || $request->ajax() || $request->is('api/*')) {
            return response()->json([
                'status' => 'success',
                'message' => "Category '{$name}' and all associated scrolls were banished.",
                'data' => [
                    'deleted_id' => $category->id,
                    'deleted_slug' => $slug,
                    'deleted_notes_count' => $deletedNotesCount,
                ],
            ]);
        }

        return redirect()->route('categories.index')
            ->with('status', 'success')
            ->with('message', "Category '{$name}' and all associated scrolls were banished.");
    }

    /**
     * Remove multiple specified categories and their associated notes atomically.
     */
    public function bulkDestroy(Request $request): RedirectResponse|JsonResponse
    {
        $user = $this->resolveUser($request);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'exists:categories,id'],
        ]);

        $categories = Category::whereIn('id', $validated['ids'])->get();

        foreach ($categories as $category) {
            if ($category->user_id !== null && $category->user_id !== $user->id) {
                abort(403, 'Unauthorized access to one or more categories.');
            }
        }

        [$deletedCount, $deletedNotesCount, $deletedIds, $deletedSlugs] = $this->mutateCategories(function () use ($categories, $user): array {
            $deletedNotesCount = 0;
            $deletedIds = [];
            $deletedSlugs = [];

            foreach ($categories as $category) {
                $category->refresh();
                $deletedNotesCount += $category->notes()->where('user_id', $user->id)->count();
                $deletedIds[] = $category->id;
                $deletedSlugs[] = $category->slug;
                $category->delete();
            }

            return [count($deletedIds), $deletedNotesCount, $deletedIds, $deletedSlugs];
        });

        if ($request->wantsJson() || $request->ajax() || $request->is('api/*')) {
            return response()->json([
                'status' => 'success',
                'message' => "Successfully banished {$deletedCount} categories and {$deletedNotesCount} associated scrolls.",
                'data' => [
                    'deleted_count' => $deletedCount,
                    'deleted_notes_count' => $deletedNotesCount,
                    'deleted_ids' => $deletedIds,
                    'deleted_slugs' => $deletedSlugs,
                ],
            ]);
        }

        return redirect()->route('categories.index')
            ->with('status', 'success')
            ->with('message', "Successfully banished {$deletedCount} categories and {$deletedNotesCount} associated scrolls.");
    }

    protected function allocateSlug(string $value, ?int $exceptId = null): string
    {
        $base = substr(Str::slug($value) ?: 'cat_'.Str::lower(Str::random(6)), 0, 64);
        $candidate = $base;
        $suffix = 1;

        while (in_array($candidate, ['all', 'pinned', 'favorites'], true) || Category::where('slug', $candidate)->when($exceptId !== null, fn (Builder $query): Builder => $query->where('id', '!=', $exceptId))->exists()) {
            $ending = '-'.++$suffix;
            $candidate = substr($base, 0, 64 - strlen($ending)).$ending;
        }

        return $candidate;
    }

    protected function mutateCategories(Closure $callback): mixed
    {
        try {
            return Cache::lock('notenux:category-mutations', 30)->block(5, fn (): mixed => DB::transaction($callback));
        } catch (LockTimeoutException) {
            abort(409, 'Categories are being updated. Please retry.');
        }
    }

    /**
     * Resolve the current authenticated user or fallback to initial scribe.
     */
    protected function resolveUser(Request $request): User
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user) {
            return $user;
        }

        $first = User::first();
        if ($first) {
            return $first;
        }

        return User::create([
            'name' => 'Retro Adventurer',
            'email' => 'hero@notenux.dev',
            'password' => bcrypt('password'),
        ]);
    }

    /**
     * Calculate player stats for retro HUD telemetry.
     *
     * @return array<string, mixed>
     */
    protected function calculatePlayerStats(?User $user = null): array
    {
        $notesQuery = Note::query();
        if ($user) {
            $notesQuery->where('user_id', $user->id);
        }

        $totalNotes = (clone $notesQuery)->count();
        $totalWords = (int) (clone $notesQuery)->sum('word_count');
        $totalXP = (int) (clone $notesQuery)->sum('xp');
        $totalChunks = $user
            ? NoteChunk::whereHas('note', fn (Builder $q) => $q->where('user_id', $user->id))->count()
            : NoteChunk::count();

        $level = max(1, (int) floor($totalXP / 150) + 1);
        $nextLevelXP = $level * 150;
        $currentLevelBaseXP = ($level - 1) * 150;
        $progressXP = $totalXP - $currentLevelBaseXP;
        $neededXP = 150;
        $percent = min(100, (int) round(($progressXP / $neededXP) * 100));

        $ranks = [
            1 => ['title' => 'Novice Scribe', 'badge' => 'fa-award', 'mana' => '30/100 MP'],
            2 => ['title' => 'Apprentice Chronicler', 'badge' => 'fa-medal', 'mana' => '55/100 MP'],
            3 => ['title' => 'Arcane Wordsmith', 'badge' => 'fa-trophy', 'mana' => '75/100 MP'],
            4 => ['title' => 'Guildmaster of Lore', 'badge' => 'fa-gem', 'mana' => '90/100 MP'],
            5 => ['title' => 'Grand Arch-Mage of Scribes', 'badge' => 'fa-crown', 'mana' => '100/100 MP'],
        ];

        $rankInfo = $ranks[min(5, $level)] ?? $ranks[5];

        return [
            'total_notes' => $totalNotes,
            'total_words' => $totalWords,
            'total_xp' => $totalXP,
            'total_chunks' => $totalChunks,
            'pinned_notes' => (clone $notesQuery)->where('is_pinned', true)->count(),
            'favorite_notes' => (clone $notesQuery)->where('is_favorite', true)->count(),
            'level' => $level,
            'progress_percent' => max(5, $percent),
            'progress_xp' => $progressXP,
            'needed_xp' => $neededXP,
            'next_level_xp' => $nextLevelXP,
            'rank_title' => $rankInfo['title'],
            'rank_badge' => $rankInfo['badge'],
            'mana' => $rankInfo['mana'],
        ];
    }
}
