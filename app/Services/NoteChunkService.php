<?php

namespace App\Services;

use App\Models\Note;
use App\Models\NoteChunk;
use Illuminate\Support\Str;

class NoteChunkService
{
    /**
     * Maximum character count per database chunk if not split by natural block boundaries.
     */
    public const MAX_CHUNK_SIZE = 1200;

    /**
     * Synchronize a note's content with its identity-backed chunks in the database.
     *
     * @return array<int, NoteChunk>
     */
    public function syncChunks(Note $note, ?string $content): array
    {
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
        $cleanText = trim(strip_tags($rawContent));
        $wordCount = $cleanText === '' ? 0 : str_word_count($cleanText);
        $charCount = mb_strlen($cleanText);
        $xp = max(10, (int) round($wordCount * 3));
        $level = max(1, (int) floor($xp / 120) + 1);

        $note->forceFill([
            'content' => $rawContent,
            'word_count' => $wordCount,
            'char_count' => $charCount,
            'xp' => $xp,
            'level' => $level,
        ])->save();

        return $createdChunks;
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
            // Split by common HTML block separators while preserving structure
            $pattern = '/(<\/?(?:p|h[1-6]|div|blockquote|pre|ul|ol|li|table|section|article)[^>]*>)/i';
            $parts = preg_split($pattern, $content, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

            $currentAccumulator = '';
            foreach ($parts as $part) {
                $currentAccumulator .= $part;
                if (strlen($currentAccumulator) >= self::MAX_CHUNK_SIZE) {
                    $rawBlocks[] = $currentAccumulator;
                    $currentAccumulator = '';
                }
            }

            if ($currentAccumulator !== '') {
                $rawBlocks[] = $currentAccumulator;
            }
        } else {
            // Plain text or Markdown chunking
            $paragraphs = preg_split('/(\r\n\r\n|\n\n)/', $content);
            $currentAccumulator = '';

            foreach ($paragraphs as $para) {
                if (strlen($currentAccumulator) + strlen($para) > self::MAX_CHUNK_SIZE && $currentAccumulator !== '') {
                    $rawBlocks[] = $currentAccumulator;
                    $currentAccumulator = $para;
                } else {
                    $currentAccumulator .= ($currentAccumulator === '' ? '' : "\n\n").$para;
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
     * Reconstruct complete text from chunks in order.
     */
    public function reassemble(Note $note): string
    {
        return $note->chunks()
            ->orderBy('chunk_index', 'asc')
            ->pluck('content')
            ->implode('');
    }
}
