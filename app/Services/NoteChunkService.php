<?php

namespace App\Services;

use App\Models\Note;
use App\Models\NoteChunk;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class NoteChunkService
{
    /**
     * Maximum character count per database chunk if not split by natural block boundaries.
     */
    public const MAX_CHUNK_SIZE = 1000;

    /**
     * Synchronize a note's content with its identity-backed chunks in the database.
     *
     * @return array<int, NoteChunk>
     */
    public function syncChunks(Note $note, ?string $content): array
    {
        return DB::transaction(function () use ($note, $content): array {
            $rawContent = (string) ($content ?? '');

            // Break content into chunks with individual identities
            $chunkPayloads = $this->splitIntoChunks($rawContent);

            // Delete existing chunks and recreate to ensure clean index sequence & unique IDs
            $note->chunks()->delete();

            $createdChunks = [];
            foreach ($chunkPayloads as $index => $payload) {
                /** @var NoteChunk $chunk */
                $chunk = $note->chunks()->create([
                    'chunk_uid' => $payload['chunk_uid'],
                    'chunk_index' => $index,
                    'chunk_type' => $payload['chunk_type'],
                    'content' => $payload['content'],
                    'byte_size' => strlen($payload['content']),
                    'checksum' => hash('sha256', $payload['content']),
                    'metadata' => $payload['metadata'] ?? null,
                ]);

                $createdChunks[] = $chunk;
            }

            // Calculate metrics
            $this->updateNoteMetrics($note, $rawContent);

            return $createdChunks;
        });
    }

    /**
     * Splits arbitrary text/HTML content into identifiable chunks.
     *
     * @return array<int, array{chunk_uid: string, chunk_type: string, content: string, metadata: array<string, mixed>}>
     */
    public function splitIntoChunks(string $content): array
    {
        if (trim($content) === '') {
            return [
                [
                    'chunk_uid' => 'chk_'.(string) Str::ulid(),
                    'chunk_type' => 'paragraph',
                    'content' => '',
                    'metadata' => ['empty' => true],
                ],
            ];
        }

        $rawBlocks = [];

        // Check if content looks like HTML
        if (preg_match('/<[a-z][\s\S]*>/i', $content)) {
            preg_match_all('/<!--[\s\S]*?-->|<\/?[a-zA-Z](?:[^>"\']|"[^"]*"|\'[^\']*\')*>|[^<]+|</', $content, $matches);
            $currentAccumulator = '';
            $openTags = [];
            $voidTags = ['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr'];
            foreach ($matches[0] as $part) {
                $currentAccumulator .= $part;
                if (preg_match('/^<\/(\w+)/', $part, $tag)) {
                    if (end($openTags) === strtolower($tag[1])) {
                        array_pop($openTags);
                    }
                } elseif (preg_match('/^<(\w+)/', $part, $tag) && ! in_array(strtolower($tag[1]), $voidTags, true) && ! str_ends_with($part, '/>')) {
                    $openTags[] = strtolower($tag[1]);
                }
                if ($openTags === [] && strlen($currentAccumulator) >= self::MAX_CHUNK_SIZE) {
                    $rawBlocks[] = $currentAccumulator;
                    $currentAccumulator = '';
                }
            }

            if ($currentAccumulator !== '') {
                $rawBlocks[] = $currentAccumulator;
            }
        } else {
            // Plain text or Markdown chunking
            $paragraphs = preg_split('/((?:\r?\n){2,})/', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
            $currentAccumulator = '';

            foreach ($paragraphs as $para) {
                if (strlen($currentAccumulator) + strlen($para) > self::MAX_CHUNK_SIZE && $currentAccumulator !== '') {
                    $rawBlocks[] = $currentAccumulator;
                    $currentAccumulator = $para;
                } else {
                    $currentAccumulator .= $para;
                }
            }

            if ($currentAccumulator !== '') {
                $rawBlocks[] = $currentAccumulator;
            }
        }

        if (empty($rawBlocks)) {
            $rawBlocks = [$content];
        }

        $chunks = [];
        foreach ($rawBlocks as $block) {
            $type = $this->detectChunkType($block);
            $chunks[] = [
                'chunk_uid' => 'chk_'.(string) Str::ulid(),
                'chunk_type' => $type,
                'content' => $block,
                'metadata' => [
                    'length' => strlen($block),
                    'has_html' => (bool) preg_match('/<[a-z][\s\S]*>/i', $block),
                ],
            ];
        }

        return $chunks;
    }

    /**
     * Detects semantic chunk type based on block content prefix.
     */
    public function detectChunkType(string $block): string
    {
        $trimmed = ltrim($block);

        if (preg_match('/^<h[1-6]/i', $trimmed) || preg_match('/^#{1,6}\s/', $trimmed)) {
            return 'heading';
        }

        if (preg_match('/^<(?:pre|code)/i', $trimmed) || preg_match('/^```/', $trimmed)) {
            return 'code';
        }

        if (preg_match('/^<blockquote/i', $trimmed) || preg_match('/^>\s/', $trimmed)) {
            return 'quote';
        }

        if (preg_match('/^<table/i', $trimmed) || preg_match('/^\|.*\|/', $trimmed)) {
            return 'table';
        }

        if (preg_match('/^[-*]\s+\[[ xX]\]/', $trimmed) || preg_match('/class=["\'][^"\']*task/i', $trimmed)) {
            return 'task';
        }

        if (preg_match('/^<(?:ul|ol|li)/i', $trimmed) || preg_match('/^[-*+]\s/', $trimmed) || preg_match('/^\d+\.\s/', $trimmed)) {
            return 'list';
        }

        return 'paragraph';
    }

    /**
     * Updates an individual chunk directly by its identity, recomputing checksum and parent note text.
     */
    public function updateChunk(NoteChunk $chunk, string $newContent, ?string $type = null): NoteChunk
    {
        return DB::transaction(function () use ($chunk, $newContent, $type): NoteChunk {
            $type = $type ?: $this->detectChunkType($newContent);

            $chunk->update([
                'content' => $newContent,
                'chunk_type' => $type,
                'byte_size' => strlen($newContent),
                'checksum' => hash('sha256', $newContent),
            ]);

            $note = $chunk->note;
            $reassembled = $this->reassemble($note);
            $this->updateNoteMetrics($note, $reassembled);

            return $chunk->fresh();
        });
    }

    /**
     * Verifies cryptographic checksum and byte size integrity for all chunks belonging to a note.
     *
     * @return array{status: string, verified: bool, total_chunks: int, valid_chunks: int, corrupted_chunks: int, details: array<int, array<string, mixed>>}
     */
    public function verifyIntegrity(Note $note): array
    {
        $chunks = $note->chunks()->orderBy('chunk_index', 'asc')->get();
        $total = $chunks->count();
        $valid = 0;
        $corrupted = 0;
        $details = [];

        foreach ($chunks as $chunk) {
            $expectedChecksum = hash('sha256', $chunk->content);
            $expectedBytes = strlen($chunk->content);
            $checksumMatches = ($chunk->checksum === $expectedChecksum);
            $bytesMatch = ($chunk->byte_size === $expectedBytes);
            $isChunkValid = $checksumMatches && $bytesMatch;

            if ($isChunkValid) {
                $valid++;
            } else {
                $corrupted++;
            }

            $details[] = [
                'chunk_uid' => $chunk->chunk_uid,
                'chunk_index' => $chunk->chunk_index,
                'is_valid' => $isChunkValid,
                'checksum_matches' => $checksumMatches,
                'bytes_match' => $bytesMatch,
            ];
        }

        $allVerified = ($total > 0 && $corrupted === 0);

        return [
            'status' => $allVerified ? 'verified' : ($total === 0 ? 'empty' : 'corrupted'),
            'verified' => $allVerified,
            'total_chunks' => $total,
            'valid_chunks' => $valid,
            'corrupted_chunks' => $corrupted,
            'details' => $details,
        ];
    }

    /**
     * Reconstruct complete text from chunks in order.
     */
    public function reassemble(Note $note): string
    {
        return $note->chunks()
            ->orderBy('chunk_index', 'asc')
            ->pluck('content')
            ->implode('');
    }

    /**
     * Deletes a specific chunk, reindexes the remaining chunks, and synchronizes the parent note.
     */
    public function deleteChunk(NoteChunk $chunk): Note
    {
        return DB::transaction(function () use ($chunk): Note {
            $note = $chunk->note;
            $chunk->delete();

            // Reindex remaining chunks
            $remaining = $note->chunks()->orderBy('chunk_index', 'asc')->get();
            foreach ($remaining as $idx => $item) {
                if ($item->chunk_index !== $idx) {
                    $item->update(['chunk_index' => $idx]);
                }
            }

            $reassembled = $this->reassemble($note);
            $this->updateNoteMetrics($note, $reassembled);

            return $note->fresh();
        });
    }

    /**
     * Reorders chunks according to the given array of chunk IDs or chunk UIDs.
     *
     * @param  array<int|string>  $orderedIdentities
     * @return Collection<int, NoteChunk>
     */
    public function reorderChunks(Note $note, array $orderedIdentities): Collection
    {
        return DB::transaction(function () use ($note, $orderedIdentities): Collection {
            $chunks = $note->chunks()->get()->keyBy(function (NoteChunk $chunk) {
                return (string) $chunk->id;
            });

            $chunksByUid = $chunks->keyBy('chunk_uid');

            $resolved = [];
            foreach ($orderedIdentities as $identity) {
                $chunk = $chunks->get((string) $identity) ?? $chunksByUid->get((string) $identity);
                if (! $chunk || in_array($chunk->id, $resolved, true)) {
                    throw ValidationException::withMessages(['order' => 'Order must contain every shard of this scroll exactly once.']);
                }
                $resolved[] = $chunk->id;
            }
            if (count($resolved) !== $chunks->count()) {
                throw ValidationException::withMessages(['order' => 'Order must contain every shard of this scroll exactly once.']);
            }

            foreach ($orderedIdentities as $index => $identity) {
                $strIdentity = (string) $identity;
                $chunk = $chunks->get($strIdentity) ?? $chunksByUid->get($strIdentity);
                if ($chunk) {
                    $chunk->update(['chunk_index' => $index]);
                }
            }

            $reassembled = $this->reassemble($note);
            $this->updateNoteMetrics($note, $reassembled);

            return $note->chunks()->orderBy('chunk_index', 'asc')->get();
        });
    }

    /**
     * Imports raw Markdown or text content into a newly created Note with chunking.
     */
    public function importMarkdown(string $rawMarkdown, ?string $filename = null, ?string $category = 'quest'): Note
    {
        return DB::transaction(function () use ($rawMarkdown, $filename, $category): Note {
            $title = null;
            if (preg_match('/^#\s+(.+)$/m', $rawMarkdown, $matches)) {
                $title = trim($matches[1]);
            } elseif ($filename) {
                $title = ucwords(str_replace(['-', '_'], ' ', pathinfo($filename, PATHINFO_FILENAME)));
            }

            if (empty($title)) {
                $title = 'Imported Scroll '.now()->format('Y-m-d H:i');
            }

            $html = Str::markdown($rawMarkdown, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
            $html = str_replace('<table>', '<table class="retro-table">', $html);

            $allowedCategories = ['quest', 'lore', 'strategy', 'spellbook', 'archive', 'general'];
            $validCategory = in_array($category, $allowedCategories, true) ? $category : 'quest';

            $note = Note::create([
                'title' => Str::limit($title, 120, ''),
                'category' => $validCategory,
                'content' => '',
                'font_family' => 'pixel',
                'font_size' => '16px',
                'theme_color' => 'blue',
            ]);

            $this->syncChunks($note, $html);

            return $note->fresh();
        });
    }

    /**
     * Recalculates metrics and saves them on the note.
     */
    protected function updateNoteMetrics(Note $note, string $rawContent): void
    {
        $cleanText = trim(html_entity_decode(strip_tags(preg_replace('/<\/(?:p|div|h[1-6]|li|tr|blockquote)>|<br\s*\/?\s*>/i', ' ', $rawContent)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $wordCount = preg_match_all('/[\p{L}\p{N}]+(?:[\x{2019}\x{0027}-][\p{L}\p{N}]+)*/u', $cleanText);
        $charCount = mb_strlen($cleanText);
        $xp = max(10, (int) round($wordCount * 3));
        $level = max(1, (int) floor($xp / 120) + 1);

        $note->forceFill([
            'content' => $rawContent,
            'word_count' => $wordCount,
            'char_count' => $charCount,
            'xp' => $xp,
            'level' => $level,
        ])->saveQuietly();
    }
}
