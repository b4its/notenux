<?php

namespace Database\Seeders;

use App\Models\Note;
use App\Services\NoteChunkService;
use Illuminate\Database\Seeder;

class NoteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(?NoteChunkService $chunkService = null): void
    {
        $chunkService ??= app(NoteChunkService::class);

        $initialNotes = [
            [
                'title' => 'Main Quest: Awakening the Retro Matrix',
                'category' => 'quest',
                'font_family' => 'pixel',
                'font_size' => '16px',
                'theme_color' => 'blue',
                'is_pinned' => true,
                'is_favorite' => true,
                'content' => <<<'HTML'
<h2>Mission Briefing: Act I</h2>
<p>Greetings, Hero! You have entered the sacred <strong>Notenux Terminal</strong>. Your quest is to document the knowledge of ancient realms and level up your chronicler stats.</p>
<blockquote>"The pen is mightier than the pixel sword, but together they forge legend." — Grand Scribe Maelor</blockquote>
<h3>Objective Checklist:</h3>
<ul>
<li>Explore the interactive dual sidebar architecture</li>
<li>Toggle between Retro Dark Theme and Arcade Light Theme</li>
<li>Paste markdown (.md), tables, and structured text directly</li>
<li>Use Zoom In and Zoom Out controls to adjust reading scale</li>
<li>Inspect database chunks with distinct identity keys</li>
</ul>
<p>Each word you scribe awards <strong>+3 XP</strong>. Reach Level 5 to unlock the Legendary Master Scribe title!</p>
HTML,
            ],
            [
                'title' => 'Spellbook: Arcane CSS Grid & 8-Bit Flexbox',
                'category' => 'spellbook',
                'font_family' => 'mono',
                'font_size' => '15px',
                'theme_color' => 'blue',
                'is_pinned' => true,
                'is_favorite' => false,
                'content' => <<<'HTML'
<h2>Chapter IV: The Grid Summoning</h2>
<p>When battling distorted layout anomalies, invoke the ancient grid containment spell:</p>
<pre><code>.retro-arcade-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
    box-shadow: 4px 4px 0px #0b1329;
}</code></pre>
<p>Remember to keep borders crisp with <em>pixel-perfect shadows</em> and <em>beveled button borders</em> to maintain authentic CRT console nostalgia.</p>
HTML,
            ],
            [
                'title' => 'Boss Raid Strategy: Memory Leak Goblin',
                'category' => 'strategy',
                'font_family' => 'sans',
                'font_size' => '16px',
                'theme_color' => 'blue',
                'is_pinned' => false,
                'is_favorite' => true,
                'content' => <<<'HTML'
<h2>Tactical Raid Plan: Level 42 Dungeon</h2>
<p>The Memory Leak Goblin lurks in unclosed event listeners and orphaned WebSocket subscriptions. Here is how our vanguard party defeats it:</p>
<table class="retro-table">
    <thead>
        <tr>
            <th>Raid Phase</th>
            <th>Vanguard Tactic</th>
            <th>Expected Loot</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Phase 1</td>
            <td>Register AbortController on network probes</td>
            <td>50 XP</td>
        </tr>
        <tr>
            <td>Phase 2</td>
            <td>Store document bodies in chunked identity DB</td>
            <td>200 XP</td>
        </tr>
        <tr>
            <td>Phase 3</td>
            <td>Clear timer intervals on component unmount</td>
            <td>Mythic Low-Latency Ring</td>
        </tr>
    </tbody>
</table>
<p>Loot reward: <strong>+450 XP</strong>, Mythic Ring of Low Latency.</p>
HTML,
            ],
            [
                'title' => 'Adventurer Lore: Sanctuary of Blue Neon',
                'category' => 'lore',
                'font_family' => 'serif',
                'font_size' => '17px',
                'theme_color' => 'blue',
                'is_pinned' => false,
                'is_favorite' => false,
                'content' => <<<'HTML'
<h2>Chronicles of the Cobalt Citadel</h2>
<p>Deep within the digital biosphere stands the <strong>Sanctuary of Blue Neon</strong>. Lit by sapphire phosphor tubes, it preserves timeless ideas across infinite revisions.</p>
<p>Scholars believe the database chunking algorithm was discovered here—allowing boundless manuscripts to be fragmented into distinct crystalline shards, each tagged with its own immortal identity.</p>
HTML,
            ],
        ];

        foreach ($initialNotes as $data) {
            $note = Note::create([
                'title' => $data['title'],
                'category' => $data['category'],
                'font_family' => $data['font_family'],
                'font_size' => $data['font_size'],
                'theme_color' => $data['theme_color'],
                'is_pinned' => $data['is_pinned'],
                'is_favorite' => $data['is_favorite'],
                'content' => $data['content'],
            ]);

            $chunkService->syncChunks($note, $data['content']);
        }
    }
}
