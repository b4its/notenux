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
        $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:64'],
            'sort' => ['nullable', 'string', 'in:updated_desc,updated_asc,created_desc,created_asc,title_asc,title_desc,word_count_desc,level_desc'],
            'pinned_only' => ['nullable', 'boolean'],
            'favorites_only' => ['nullable', 'boolean'],
        ]);
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
            case 'title_desc':
                $query->orderBy('is_pinned', 'desc')->orderBy('title', 'desc');
                break;
            case 'created_asc':
                $query->orderBy('is_pinned', 'desc')->orderBy('created_at', 'asc');
                break;
            case 'created_desc':
                $query->orderBy('is_pinned', 'desc')->orderBy('created_at', 'desc');
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
            'is_pinned' => ['nullable', 'boolean'],
            'is_favorite' => ['nullable', 'boolean'],
        ]);

        $note = DB::transaction(function () use ($validated): Note {
            $note = Note::create([
                'title' => $validated['title'],
                'category' => $validated['category'] ?? 'quest',
                'font_family' => $validated['font_family'] ?? 'pixel',
                'font_size' => $validated['font_size'] ?? '16px',
                'theme_color' => $validated['theme_color'] ?? 'blue',
                'is_pinned' => $validated['is_pinned'] ?? false,
                'is_favorite' => $validated['is_favorite'] ?? false,
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
     * Remove multiple specified notes and their chunks atomically.
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'exists:notes,id'],
        ]);

        $deletedIds = DB::transaction(function () use ($validated): array {
            $notes = Note::whereIn('id', $validated['ids'])->get();
            $ids = [];

            foreach ($notes as $note) {
                $ids[] = $note->id;
                $note->delete();
            }

            return $ids;
        });

        $count = count($deletedIds);

        return response()->json([
            'status' => 'success',
            'message' => "Successfully banished {$count} scrolls into the void.",
            'data' => [
                'deleted_count' => $count,
                'deleted_ids' => $deletedIds,
            ],
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
     * Export note in Markdown (.md), Plain Text (.txt), HTML (.html), or JSON (.json) format.
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
            $text .= $this->convertToPlainText($note->content);

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

        if ($format === 'json') {
            $data = [
                'id' => $note->id,
                'uuid' => $note->uuid,
                'title' => $note->title,
                'category' => $note->category,
                'content' => $note->content,
                'font_family' => $note->font_family,
                'font_size' => $note->font_size,
                'theme_color' => $note->theme_color,
                'word_count' => $note->word_count,
                'char_count' => $note->char_count,
                'reading_time' => $note->reading_time,
                'level' => $note->level,
                'xp' => $note->xp,
                'is_pinned' => $note->is_pinned,
                'is_favorite' => $note->is_favorite,
                'created_at' => $note->created_at?->toIso8601String(),
                'updated_at' => $note->updated_at?->toIso8601String(),
                'chunks' => $note->chunks()->get()->map(fn ($chunk) => [
                    'id' => $chunk->id,
                    'chunk_uid' => $chunk->chunk_uid,
                    'chunk_index' => $chunk->chunk_index,
                    'chunk_type' => $chunk->chunk_type,
                    'content' => $chunk->content,
                    'byte_size' => $chunk->byte_size,
                    'checksum' => $chunk->checksum,
                    'metadata' => $chunk->metadata,
                ])->values()->all(),
            ];

            return response((string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 200, [
                'Content-Type' => 'application/json; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}.json\"",
            ]);
        }

        // Default Markdown (.md)
        $markdown = "# {$note->title}\n\n";
        $markdown .= "> Category: {$note->category} | Level: {$note->level} | Word Count: {$note->word_count}\n\n";
        $markdown .= $this->convertToMarkdown((string) ($note->content ?? ''))."\n";

        return response($markdown, 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}.md\"",
        ]);
    }

    /**
     * Convert rich HTML content to clean, readable Markdown syntax.
     */
    public function convertToMarkdown(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $md = $html;

        // Links
        $md = preg_replace('/<a\b[^>]*href="([^"]*)"[^>]*>([\s\S]*?)<\/a>/i', '[$2]($1)', $md);

        // Images
        $md = preg_replace_callback('/<img\b[^>]*>/i', function ($m) {
            preg_match('/\bsrc="([^"]*)"/i', $m[0], $src);
            preg_match('/\balt="([^"]*)"/i', $m[0], $alt);
            $url = $src[1] ?? '';
            $desc = $alt[1] ?? '';

            return "![{$desc}]({$url})";
        }, $md);

        // Markdown Tables
        $md = preg_replace_callback('/<table[^>]*>([\s\S]*?)<\/table>/i', function ($match) {
            $tableContent = $match[1];
            preg_match_all('/<tr[^>]*>[\s\S]*?<\/tr>/i', $tableContent, $trMatches);
            $rows = [];
            foreach ($trMatches[0] as $idx => $trHtml) {
                preg_match_all('/<(?:th|td)[^>]*>([\s\S]*?)<\/(?:th|td)>/i', $trHtml, $cellMatches);
                $cells = array_map(function ($c) {
                    $inner = preg_replace_callback('/<img\b[^>]*>/i', function ($m) {
                        preg_match('/\bsrc="([^"]*)"/i', $m[0], $src);
                        preg_match('/\balt="([^"]*)"/i', $m[0], $alt);
                        $url = $src[1] ?? '';
                        $desc = $alt[1] ?? '';

                        return "![{$desc}]({$url})";
                    }, $c);
                    $inner = preg_replace('/<br\s*\/?>/i', ' ', $inner);
                    $clean = trim(strip_tags($inner));

                    return str_replace('|', '&#124;', $clean);
                }, $cellMatches[1]);
                if (! empty($cells)) {
                    $rows[] = '| '.implode(' | ', $cells).' |';
                    if ($idx === 0) {
                        $rows[] = '| '.implode(' | ', array_fill(0, count($cells), '---')).' |';
                    }
                }
            }

            return ! empty($rows) ? "\n\n".implode("\n", $rows)."\n\n" : '';
        }, $md);

        // Task / Checklist items
        $md = preg_replace('/<li[^>]*>\s*<input[^>]*checked[^>]*>\s*([\s\S]*?)<\/li>/i', "- [x] $1\n", $md);
        $md = preg_replace('/<li[^>]*>\s*<input[^>]*type="checkbox"[^>]*>\s*([\s\S]*?)<\/li>/i', "- [ ] $1\n", $md);

        // Headings 1 through 6
        for ($i = 6; $i >= 1; $i--) {
            $prefix = str_repeat('#', $i);
            $md = preg_replace("/<h{$i}\b[^>]*>([\s\S]*?)<\/h{$i}>/i", "\n\n{$prefix} $1\n\n", $md);
        }

        // Paragraphs & Blockquotes
        $md = preg_replace('/<p\b[^>]*>([\s\S]*?)<\/p>/i', "$1\n\n", $md);
        $md = preg_replace('/<blockquote\b[^>]*>([\s\S]*?)<\/blockquote>/i', "> $1\n\n", $md);

        // Code blocks
        $md = preg_replace('/<pre\b[^>]*>\s*<code\b[^>]*>([\s\S]*?)<\/code>\s*<\/pre>/i', "```\n$1\n```\n\n", $md);
        $md = preg_replace('/<code\b[^>]*>([\s\S]*?)<\/code>/i', '`$1`', $md);

        // Inline formatting
        $md = preg_replace('/<(?:strong|b)\b[^>]*>([\s\S]*?)<\/(?:strong|b)>/i', '**$1**', $md);
        $md = preg_replace('/<(?:em|i)\b[^>]*>([\s\S]*?)<\/(?:em|i)>/i', '*$1*', $md);
        $md = preg_replace('/<(?:del|s)\b[^>]*>([\s\S]*?)<\/(?:del|s)>/i', '~~$1~~', $md);

        // Lists
        $md = preg_replace('/<li\b[^>]*>([\s\S]*?)<\/li>/i', "- $1\n", $md);
        $md = preg_replace('/<\/?(?:ul|ol)\b[^>]*>/i', "\n", $md);

        // Dividers & Breaks
        $md = preg_replace('/<hr\b[^>]*>/i', "\n---\n\n", $md);
        $md = preg_replace('/<br\b[^>]*>/i', "\n", $md);

        $md = strip_tags($md);
        $md = html_entity_decode($md, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $md = preg_replace("/\n{3,}/", "\n\n", $md);

        return trim($md);
    }

    /**
     * Convert rich HTML content to clean, unformatted plain text.
     */
    public function convertToPlainText(?string $html): string
    {
        if (blank($html)) {
            return '';
        }

        $text = (string) $html;
        $text = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/i', '', $text);
        $text = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/i', '', $text);
        $text = preg_replace('/<!--[\s\S]*?-->/', '', $text);
        $text = preg_replace('/<br\s*[\/]?>/i', "\n", $text);

        // Task checkboxes
        $text = preg_replace('/\s*<li[^>]*>\s*(?:<label[^>]*>\s*)?<input\b[^>]*?\btype=["\']checkbox["\'][^>]*?\bchecked\b[^>]*>\s*/i', "\n[x] ", $text);
        $text = preg_replace('/\s*<li[^>]*>\s*(?:<label[^>]*>\s*)?<input\b[^>]*?\bchecked\b[^>]*?\btype=["\']checkbox["\'][^>]*>\s*/i', "\n[x] ", $text);
        $text = preg_replace('/\s*<li[^>]*>\s*(?:<label[^>]*>\s*)?<input\b[^>]*?\btype=["\']checkbox["\'][^>]*>\s*/i', "\n[ ] ", $text);
        $text = preg_replace('/<input\b[^>]*?\btype=["\']checkbox["\'][^>]*?\bchecked\b[^>]*>/i', '[x] ', $text);
        $text = preg_replace('/<input\b[^>]*?\bchecked\b[^>]*?\btype=["\']checkbox["\'][^>]*>/i', '[x] ', $text);
        $text = preg_replace('/<input\b[^>]*?\btype=["\']checkbox["\'][^>]*>/i', '[ ] ', $text);

        // Ordered lists
        $text = preg_replace_callback('/<ol[^>]*>(.*?)<\/ol>/is', function ($matches) {
            $index = 1;

            return preg_replace_callback('/\s*<li[^>]*>(.*?)<\/li>\s*/is', function ($li) use (&$index) {
                return ($index++).'. '.trim($li[1])."\n";
            }, $matches[1]);
        }, $text);

        // Unordered lists
        $text = preg_replace('/\s*<li[^>]*>/i', "\n• ", $text);
        $text = preg_replace('/<\/li>\s*/i', "\n", $text);

        // Table cells and rows
        $text = preg_replace('/<\/(td|th)>\s*<(td|th)[^>]*>/i', "\t", $text);
        $text = preg_replace('/<tr[^>]*>\s*/i', '', $text);
        $text = preg_replace('/<\/tr>\s*/i', "\n", $text);
        $text = preg_replace('/<\/?(thead|tbody|tfoot)[^>]*>\s*/i', '', $text);

        // Block elements
        $text = preg_replace('/<\/(p|div|h[1-6]|blockquote|pre|table)>/i', "\n\n", $text);

        // Horizontal rules
        $text = preg_replace('/<hr\s*[\/]?>/i', "\n---\n", $text);

        // Strip remaining HTML tags & decode entities
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Normalize carriage returns and excessive newlines
        $text = preg_replace("/\r\n|\r/", "\n", $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text);
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
                'note' => $note->fresh()->load('chunks'),
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
                'note' => $updatedNote->load('chunks'),
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
                'note' => $note->fresh()->load('chunks'),
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
            'progress_xp' => $progressXP,
            'needed_xp' => $neededXP,
            'next_level_xp' => $nextLevelXP,
        ];
    }
}
