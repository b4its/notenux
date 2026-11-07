<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Note;
use App\Models\NoteChunk;
use App\Models\User;
use App\Services\NoteChunkService;
use App\Services\NoteContentSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class NoteController extends Controller
{
    public function __construct(
        protected NoteChunkService $chunkService,
        protected NoteContentSanitizer $sanitizer
    ) {}

    /**
     * Display the main panel or return notes list as JSON.
     * Prevents excessive data loading by omitting heavy content on index queries.
     */
    public function index(Request $request): View|JsonResponse
    {
        $request->validate(['q' => ['nullable', 'string', 'max:255'], 'category' => ['nullable', 'string', 'max:64'], 'sort' => ['nullable', 'string']]);
        $query = Note::select([
            'id',
            'uuid',
            'title',
            'category',
            'font_family',
            'font_size',
            'theme_color',
            'word_count',
            'char_count',
            'is_pinned',
            'is_favorite',
            'level',
            'xp',
            'created_at',
            'updated_at',
        ])->withCount('chunks');

        if ($search = $request->input('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        if ($category = $request->input('category')) {
            if ($category !== 'all') {
                $query->where('category', $category);
            }
        }

        if ($request->boolean('pinned_only')) {
            $query->where('is_pinned', true);
        }

        if ($request->boolean('favorites_only')) {
            $query->where('is_favorite', true);
        }

        // Sorting
        $sort = $request->input('sort', 'updated_desc');
        switch ($sort) {
            case 'title_asc':
                $query->orderBy('is_pinned', 'desc')->orderBy('title', 'asc');
                break;
            case 'word_count_desc':
                $query->orderBy('is_pinned', 'desc')->orderBy('word_count', 'desc');
                break;
            case 'level_desc':
                $query->orderBy('is_pinned', 'desc')->orderBy('level', 'desc');
                break;
            case 'updated_asc':
                $query->orderBy('is_pinned', 'desc')->orderBy('updated_at', 'asc');
                break;
            case 'updated_desc':
            default:
                $query->prioritized();
                break;
        }

        $notes = $query->get();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'data' => $notes,
            ]);
        }

        $stats = $this->calculateStats();
        $initialNote = $notes->first();
        if ($initialNote) {
            $initialNote = Note::with('chunks')->find($initialNote->id);
            $initialNote->content = $this->sanitizer->sanitize($initialNote->content);
        }

        $user = $request->user() ?? User::first();
        $categories = Category::forUser($user)
            ->withCount('notes')
            ->orderBy('is_system', 'desc')
            ->orderBy('name', 'asc')
            ->get();

        if ($categories->isEmpty()) {
            foreach (Category::defaultCategories() as $def) {
                Category::firstOrCreate(['slug' => $def['slug']], $def);
            }
            $categories = Category::forUser($user)
                ->withCount('notes')
                ->orderBy('is_system', 'desc')
                ->orderBy('name', 'asc')
                ->get();
        }

        return view('panel', [
            'notes' => $notes,
            'activeNote' => $initialNote,
            'stats' => $stats,
            'categories' => $categories,
        ]);
    }

    /**
     * Store a freshly created note in storage with identity-backed chunks.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:64'],
            'content' => ['nullable', 'string'],
            'font_family' => ['nullable', 'string', 'in:pixel,arcade,mono,sans,serif'],
            'font_size' => ['nullable', 'string', 'max:16'],
            'theme_color' => ['nullable', 'string', 'max:32'],
        ]);

        $note = DB::transaction(function () use ($validated): Note {
            $note = Note::create([
                'title' => $validated['title'],
                'category' => $validated['category'] ?? 'quest',
                'font_family' => $validated['font_family'] ?? 'pixel',
                'font_size' => $validated['font_size'] ?? '16px',
                'theme_color' => $validated['theme_color'] ?? 'blue',
                'content' => $this->sanitizer->sanitize($validated['content'] ?? ''),
            ]);

            $this->chunkService->syncChunks($note, $note->content);

            return $note;
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Scroll inscribed and chunked into memory matrix!',
            'data' => $note->fresh()->load('chunks'),
        ], 201);
    }

    /**
     * Display the specified note with chunks and gamification data.
     */
    public function show(Note $note): JsonResponse
    {
        $note->load('chunks');
        $note->content = $this->sanitizer->sanitize($note->content);

        return response()->json([
            'status' => 'success',
            'data' => $note,
        ]);
    }

    /**
     * Update the specified note and synchronize its chunks.
     */
    public function update(Request $request, Note $note): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'category' => ['sometimes', 'nullable', 'string', 'max:64'],
            'content' => ['sometimes', 'nullable', 'string'],
            'font_family' => ['sometimes', 'string', 'in:pixel,arcade,mono,sans,serif'],
            'font_size' => ['sometimes', 'string', 'max:16'],
            'theme_color' => ['sometimes', 'string', 'max:32'],
            'is_pinned' => ['sometimes', 'boolean'],
            'is_favorite' => ['sometimes', 'boolean'],
        ]);

        DB::transaction(function () use ($note, $validated): void {
            if (array_key_exists('content', $validated)) {
                $validated['content'] = $this->sanitizer->sanitize($validated['content']);
            }
            $note->fill($validated);
            $note->save();

            if (array_key_exists('content', $validated)) {
                $this->chunkService->syncChunks($note, $validated['content']);
            }
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Scroll updated and chunks harmonized!',
            'data' => $note->fresh()->load('chunks'),
        ]);
    }

    /**
     * Remove the specified note and its chunks.
     */
    public function destroy(Note $note): JsonResponse
    {
        $title = $note->title;
        $note->delete();

        return response()->json([
            'status' => 'success',
            'message' => "Scroll '{$title}' banish into the void.",
        ]);
    }

    /**
     * Toggle pinned state for quick access.
     */
    public function togglePin(Note $note): JsonResponse
    {
        $note->is_pinned = ! $note->is_pinned;
        $note->save();

        return response()->json([
            'status' => 'success',
            'is_pinned' => $note->is_pinned,
            'message' => $note->is_pinned ? 'Scroll pinned to spellbook!' : 'Scroll unpinned.',
        ]);
    }

    /**
     * Toggle favorite state for bookmarking.
     */
    public function toggleFavorite(Note $note): JsonResponse
    {
        $note->is_favorite = ! $note->is_favorite;
        $note->save();

        return response()->json([
            'status' => 'success',
            'is_favorite' => $note->is_favorite,
            'message' => $note->is_favorite ? 'Scroll marked as favorite!' : 'Scroll removed from favorites.',
        ]);
    }

    /**
     * Duplicate an existing note with all its contents.
     */
    public function duplicate(Note $note): JsonResponse
    {
        $clone = DB::transaction(function () use ($note): Note {
            $clone = $note->replicate(['uuid']);
            $clone->title = mb_substr($note->title, 0, 248).' (Copy)';
            $clone->is_pinned = false;
            $clone->save();

            $this->chunkService->syncChunks($clone, $this->sanitizer->sanitize($note->content));

            return $clone;
        });

        return response()->json([
            'status' => 'success',
            'message' => "Scroll duplicated as '{$clone->title}'!",
            'data' => $clone->fresh()->load('chunks'),
        ], 201);
    }

    /**
     * Export note in Markdown (.md), Plain Text (.txt), or HTML (.html) format.
     */
    public function export(Note $note, string $format = 'md'): Response
    {
        $note->content = $this->sanitizer->sanitize($note->content);
        $format = strtolower($format);
        $filename = Str::slug($note->title) ?: 'scroll';

        if ($format === 'txt') {
            $text = "TITLE: {$note->title}\n";
            $text .= "CATEGORY: {$note->category}\n";
            $text .= "WORDS: {$note->word_count} | XP: {$note->xp}\n";
            $text .= "UPDATED: {$note->updated_at}\n";
            $text .= str_repeat('-', 40)."\n\n";
            $text .= trim(strip_tags($note->content));

            return response($text, 200, [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}.txt\"",
            ]);
        }

        if ($format === 'html') {
            $title = e($note->title);
            $html = "<!DOCTYPE html>\n<html>\n<head>\n<meta charset=\"utf-8\">\n<title>{$title}</title>\n";
            $html .= "<style>body{font-family:sans-serif;max-width:800px;margin:2rem auto;line-height:1.6;padding:1rem}table{border-collapse:collapse;width:100%}th,td{border:1px solid #ccc;padding:8px}</style>\n";
            $html .= "</head>\n<body>\n<h1>{$title}</h1>\n{$note->content}\n</body>\n</html>";

            return response($html, 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}.html\"",
            ]);
        }

        // Default Markdown (.md)
        $markdown = "# {$note->title}\n\n";
        $markdown .= "> Category: {$note->category} | Level: {$note->level} | Word Count: {$note->word_count}\n\n";

        $content = (string) ($note->content ?? '');
        $content = preg_replace('/<h2>(.*?)<\/h2>/i', "## $1\n", $content);
        $content = preg_replace('/<h3>(.*?)<\/h3>/i', "### $1\n", $content);
        $content = preg_replace('/<p>(.*?)<\/p>/i', "$1\n\n", $content);
        $content = preg_replace('/<strong>(.*?)<\/strong>/i', '**$1**', $content);
        $content = preg_replace('/<em>(.*?)<\/em>/i', '*$1*', $content);
        $content = preg_replace('/<blockquote>(.*?)<\/blockquote>/i', "> $1\n\n", $content);
        $content = strip_tags($content);
        $markdown .= trim($content)."\n";

        return response($markdown, 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}.md\"",
        ]);
    }

    /**
     * Retrieve chunks specifically to inspect identity keys and checksums.
     */
    public function chunks(Note $note): JsonResponse
    {
        $chunks = $note->chunks()->get();

        return response()->json([
            'status' => 'success',
            'note_id' => $note->id,
            'note_uuid' => $note->uuid,
            'total_chunks' => $chunks->count(),
            'total_bytes' => $chunks->sum('byte_size'),
            'chunks' => $chunks,
        ]);
    }

    /**
     * Update an individual chunk directly by its identity.
     */
    public function updateChunk(Request $request, Note $note, NoteChunk $chunk): JsonResponse
    {
        if ($chunk->note_id !== $note->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Chunk does not belong to this scroll.',
            ], 404);
        }

        $validated = $request->validate([
            'content' => ['required', 'string'],
            'chunk_type' => ['nullable', 'string', 'in:paragraph,heading,code,quote,table,task,list'],
        ]);

        $updatedChunk = $this->chunkService->updateChunk(
            $chunk,
            $this->sanitizer->sanitize($validated['content']),
            $validated['chunk_type'] ?? null
        );

        return response()->json([
            'status' => 'success',
            'message' => "Shard {$chunk->chunk_uid} updated & checksum recomputed!",
            'data' => [
                'chunk' => $updatedChunk,
                'note' => $note->fresh(),
            ],
        ]);
    }

    /**
     * Run a cryptographic audit verifying all chunks' checksums and integrity.
     */
    public function verifyChunks(Note $note): JsonResponse
    {
        $report = $this->chunkService->verifyIntegrity($note);

        return response()->json([
            'status' => 'success',
            'note_id' => $note->id,
            'note_uuid' => $note->uuid,
            'data' => $report,
        ]);
    }

    /**
     * Delete an individual chunk directly from a note.
     */
    public function deleteChunk(Note $note, NoteChunk $chunk): JsonResponse
    {
        if ($chunk->note_id !== $note->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Chunk does not belong to this scroll.',
            ], 404);
        }

        $updatedNote = $this->chunkService->deleteChunk($chunk);

        return response()->json([
            'status' => 'success',
            'message' => 'Shard deleted and parent scroll reassembled successfully.',
            'data' => [
                'note' => $updatedNote,
                'remaining_chunks' => $updatedNote->chunks()->count(),
            ],
        ]);
    }

    /**
     * Reorder chunks for a note.
     */
    public function reorderChunks(Request $request, Note $note): JsonResponse
    {
        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['required', 'regex:/^(?:[0-9]+|chk_[A-Za-z0-9_]+)$/'],
        ]);

        $reordered = $this->chunkService->reorderChunks($note, $validated['order']);

        return response()->json([
            'status' => 'success',
            'message' => 'Shards reordered and scroll reassembled successfully.',
            'data' => [
                'chunks' => $reordered,
                'note' => $note->fresh(),
            ],
        ]);
    }

    /**
     * Import a Markdown or plain text file / payload into a new Note with chunking.
     */
    public function import(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['nullable', 'file', 'max:5120'],
            'content' => ['required_without:file', 'nullable', 'string'],
            'category' => ['nullable', 'string', 'max:64'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $rawMarkdown = '';
        $filename = null;

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $filename = $file->getClientOriginalName();
            $rawMarkdown = file_get_contents($file->getRealPath());
        } else {
            $rawMarkdown = $validated['content'] ?? '';
            $filename = $validated['title'] ?? null;
        }

        $category = $validated['category'] ?? 'quest';
        $note = $this->chunkService->importMarkdown($rawMarkdown, $filename, $category);

        if (! empty($validated['title'])) {
            $note->update(['title' => $validated['title']]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Scroll imported and indexed successfully into database shards!',
            'data' => $note->load('chunks'),
        ], 201);
    }

    /**
     * Return gamified stats overview for the player.
     */
    public function stats(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $this->calculateStats(),
        ]);
    }

    /**
     * Calculate player ranking and gamified stats.
     *
     * @return array<string, mixed>
     */
    protected function calculateStats(): array
    {
        $totalNotes = Note::count();
        $totalWords = (int) Note::sum('word_count');
        $totalXP = (int) Note::sum('xp');
        $totalChunks = NoteChunk::count();

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
            'total_chunks' => $totalChunks,
            'total_xp' => $totalXP,
            'level' => $level,
            'rank_title' => $rankInfo['title'],
            'rank_badge' => $rankInfo['badge'],
            'mana' => $rankInfo['mana'],
            'progress_percent' => max(5, $percent),
            'next_level_xp' => $nextLevelXP,
        ];
    }
}
