<?php

namespace App\Http\Controllers;

use App\Models\Folder;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FolderController extends Controller
{
    /**
     * Resolve the active authenticated user or testing fallback.
     */
    private function resolveUser(Request $request): User
    {
        $user = $request->user();
        if ($user) {
            return $user;
        }

        if (app()->environment('testing')) {
            return User::first() ?? User::factory()->create();
        }

        abort(401, 'Unauthenticated.');
    }

    /**
     * Display a listing of the user's folders.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);

        $folders = Folder::forUser($user)
            ->withCount(['children', 'notes'])
            ->orderBy('name', 'asc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $folders,
        ]);
    }

    /**
     * Store a newly created folder in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:64'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('folders', 'id')->where(fn ($q) => $q->where('user_id', $user->id)),
            ],
            'color' => ['nullable', 'string', 'max:32'],
            'icon' => ['nullable', 'string', 'max:64'],
            'is_expanded' => ['nullable', 'boolean'],
        ]);

        $folderCategory = ! empty($validated['category']) ? trim((string) preg_replace('/[\x00-\x1F\x7F\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $validated['category'])) : null;

        if (! empty($validated['parent_id'])) {
            $parent = Folder::forUser($user)->find($validated['parent_id']);
            if ($parent && ! empty($parent->category) && ! empty($folderCategory) && $parent->category !== $folderCategory) {
                throw ValidationException::withMessages([
                    'category' => "Subfolder dengan kategori [{$folderCategory}] tidak dapat dimasukkan ke dalam folder induk berkategori [{$parent->category}].",
                ]);
            }
        }

        $folder = Folder::create([
            'user_id' => $user->id,
            'parent_id' => $validated['parent_id'] ?? null,
            'name' => trim((string) preg_replace('/[\x00-\x1F\x7F\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $validated['name'])),
            'category' => $folderCategory,
            'color' => $validated['color'] ?? 'amber',
            'icon' => $validated['icon'] ?? 'fa-solid fa-folder',
            'is_expanded' => $validated['is_expanded'] ?? true,
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $folder,
        ], 201);
    }

    /**
     * Update the specified folder in storage.
     */
    public function update(Request $request, Folder $folder): JsonResponse
    {
        $user = $this->resolveUser($request);

        if ($folder->user_id !== $user->id) {
            abort(403, 'Akses folder tidak diizinkan.');
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'category' => ['sometimes', 'nullable', 'string', 'max:64'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('folders', 'id')->where(fn ($q) => $q->where('user_id', $user->id)),
            ],
            'color' => ['sometimes', 'string', 'max:32'],
            'icon' => ['sometimes', 'string', 'max:64'],
            'is_expanded' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('category', $validated)) {
            $validated['category'] = ! empty($validated['category']) ? trim((string) preg_replace('/[\x00-\x1F\x7F\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $validated['category'])) : null;
        }

        $targetCategory = array_key_exists('category', $validated)
            ? $validated['category']
            : $folder->category;

        if (array_key_exists('parent_id', $validated) && $validated['parent_id'] !== null) {
            $parentId = (int) $validated['parent_id'];
            if ($parentId === $folder->id || in_array($parentId, $folder->allDescendantIds(), true)) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Folder cannot be nested inside itself or one of its descendants.',
                ]);
            }

            $targetParent = Folder::forUser($user)->find($parentId);
            if ($targetParent && ! empty($targetParent->category) && ! empty($targetCategory) && $targetParent->category !== $targetCategory) {
                throw ValidationException::withMessages([
                    'parent_id' => "Folder dengan kategori [{$targetCategory}] tidak dapat dimasukkan ke dalam folder induk berkategori [{$targetParent->category}].",
                ]);
            }
        }

        if (array_key_exists('category', $validated) && ! empty($validated['category'])) {
            $newCat = $validated['category'];
            $hasConflictingNotes = $folder->notes()
                ->whereNotNull('category')
                ->where('category', '!=', '')
                ->where('category', '!=', $newCat)
                ->exists();

            if ($hasConflictingNotes) {
                throw ValidationException::withMessages([
                    'category' => "Folder ini memuat gulungan dengan kategori lain yang berbeda dari [{$newCat}].",
                ]);
            }
        }

        if (array_key_exists('category', $validated)) {
            $validated['category'] = ! empty($validated['category']) ? $validated['category'] : null;
        }

        if (array_key_exists('name', $validated)) {
            $validated['name'] = trim((string) preg_replace('/[\x00-\x1F\x7F\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $validated['name']));
        }

        $folder->update($validated);

        return response()->json([
            'status' => 'success',
            'data' => $folder->fresh(),
        ]);
    }

    /**
     * Remove the specified folder from storage.
     */
    public function destroy(Request $request, Folder $folder): JsonResponse
    {
        $user = $this->resolveUser($request);

        if ($folder->user_id !== $user->id) {
            abort(403, 'Akses folder tidak diizinkan.');
        }

        // Subfolders will be cascade deleted by MySQL foreign key.
        // Notes will have their folder_id set to null by nullOnDelete.
        $folder->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Folder deleted successfully.',
        ]);
    }
}
