<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Models\NoteChunk;
use App\Services\NoteChunkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NoteController extends Controller
{
    public function __construct(
        protected NoteChunkService $chunkService
    ) {}

    /**
     * Display the main panel or return notes list as JSON.
     */
    public function index(Request $request): View|JsonResponse
    {
        $query = Note::withCount('chunks')->prioritized();

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
            $initialNote->load('chunks');
        }

        return view('panel', [
            'notes' => $notes,
            'activeNote' => $initialNote,
            'stats' => $stats,
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
            'font_family' => ['nullable', 'string', 'in:pixel,sans,mono,serif'],
            'font_size' => ['nullable', 'string', 'max:16'],
            'theme_color' => ['nullable', 'string', 'max:32'],
        ]);

        $note = Note::create([
            'title' => $validated['title'],
            'category' => $validated['category'] ?? 'quest',
            'font_family' => $validated['font_family'] ?? 'pixel',
            'font_size' => $validated['font_size'] ?? '16px',
            'theme_color' => $validated['theme_color'] ?? 'blue',
            'content' => $validated['content'] ?? '',
        ]);

        $chunks = $this->chunkService->syncChunks($note, $validated['content'] ?? '');

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
            'font_family' => ['sometimes', 'string', 'in:pixel,sans,mono,serif'],
            'font_size' => ['sometimes', 'string', 'max:16'],
            'theme_color' => ['sometimes', 'string', 'max:32'],
            'is_pinned' => ['sometimes', 'boolean'],
            'is_favorite' => ['sometimes', 'boolean'],
        ]);

        $note->fill($validated);
        $note->save();

        if ($request->has('content')) {
            $this->chunkService->syncChunks($note, $request->input('content'));
        }

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
