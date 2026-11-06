<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Notenux') }} - Retro Gamified Notes Panel</title>

    <!-- Google Fonts for Retro Gamification & Multi-Font Typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;600&family=Instrument+Sans:wght@400;500;600;700&family=Merriweather:ital,wght@0,300;0,400;0,700;1,300&family=Press+Start+2P&family=VT323&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        /* Sidebar collapse behavior for desktop */
        #notenux-sidebar.collapsed {
            width: 76px;
        }
        #notenux-sidebar.collapsed .sidebar-full-content {
            display: none;
        }
        #notenux-sidebar.collapsed .sidebar-collapsed-content {
            display: flex;
        }
        .sidebar-collapsed-content {
            display: none;
        }
    </style>
</head>
<body class="retro-crt-screen bg-[var(--bg-main)] text-[var(--text-main)] min-h-screen font-sans flex flex-col antialiased selection:bg-blue-600 selection:text-white">

    <!-- Top Navigation Bar (Retro Console Header) -->
    <header class="sticky top-0 z-30 bg-[var(--bg-surface)] border-b-2 border-black dark:border-blue-900 shadow-[0_4px_0_rgba(0,0,0,0.25)] px-3 py-2.5 sm:px-6">
        <div class="flex items-center justify-between gap-2 max-w-7xl mx-auto">
            <!-- Left Branding & Mobile Drawer Toggle -->
            <div class="flex items-center gap-2 sm:gap-3">
                <!-- Mobile Menu Button -->
                <button id="mobile-toggle-btn"
                        class="lg:hidden arcade-btn bg-blue-600 text-white p-2 rounded text-xs"
                        aria-label="Toggle Navigation Drawer">
                    ☰
                </button>

                <!-- Desktop Sidebar Toggle Button -->
                <button id="sidebar-toggle-btn"
                        class="hidden lg:inline-flex arcade-btn bg-blue-600 hover:bg-blue-500 text-white p-2 rounded text-xs"
                        title="Toggle Collapsible Sidebar">
                    ⏪
                </button>

                <!-- Logo & Brand Badge -->
                <div class="flex items-center gap-2">
                    <span class="text-xl sm:text-2xl filter drop-shadow-[2px_2px_0px_#000]">👾</span>
                    <div>
                        <div class="flex items-center gap-1.5">
                            <span class="font-pixel text-xs sm:text-sm text-blue-600 dark:text-cyan-400 tracking-wider">NOTENUX</span>
                            <span class="text-[9px] font-mono bg-blue-900/20 text-blue-600 dark:text-cyan-300 px-1 py-0.5 rounded border border-blue-500/40 font-bold uppercase">v2.0 CHUNK-DB</span>
                        </div>
                        <p class="text-[10px] font-mono opacity-60 hidden sm:block">Retro Gamified Markdown & Memory Shard Matrix</p>
                    </div>
                </div>
            </div>

            <!-- Right Controls: Audio SFX, Theme Switcher, Quick Stats -->
            <div class="flex items-center gap-2 sm:gap-3">
                <!-- Sound Synthesizer SFX Button -->
                <button id="sound-toggle-btn"
                        class="arcade-btn bg-blue-100 hover:bg-blue-200 dark:bg-blue-950 dark:hover:bg-blue-900 text-blue-900 dark:text-blue-300 px-2.5 py-1.5 rounded text-xs font-mono"
                        title="Toggle 8-bit Audio Synthesizer">
                    🔊 <span class="hidden sm:inline">SFX: ON</span>
                </button>

                <!-- Light / Dark Theme Switcher -->
                <button id="theme-toggle-btn"
                        class="arcade-btn bg-amber-300 hover:bg-amber-400 dark:bg-blue-900 dark:hover:bg-blue-800 text-black dark:text-cyan-300 px-2.5 py-1.5 rounded text-xs font-mono"
                        title="Toggle Light / Dark Mode">
                    🌙 <span class="hidden sm:inline">NIGHT</span>
                </button>

                <!-- Database Chunk Inspector Trigger -->
                <button id="inspect-chunks-btn"
                        class="arcade-btn bg-cyan-600 hover:bg-cyan-500 text-white px-2.5 py-1.5 rounded text-xs font-mono"
                        title="Inspect Live Database Chunks & Shard Keys">
                    📦 <span class="hidden md:inline">DB CHUNKS</span>
                </button>
            </div>
        </div>
    </header>

    <!-- Main Workspace Container -->
    <div class="flex-1 flex overflow-hidden relative">

        <!-- Mobile Sidebar Backdrop Overlay -->
        <div id="sidebar-overlay"
             class="fixed inset-0 bg-black/60 backdrop-blur-xs z-35 hidden lg:hidden transition-opacity"></div>

        <!-- ============================================================================== -->
        <!-- INTERACTIVE COLLAPSIBLE SIDEBAR -->
        <!-- ============================================================================== -->
        <aside id="notenux-sidebar"
               class="fixed inset-y-0 left-0 z-40 lg:static lg:z-auto w-80 shrink-0 bg-[var(--bg-surface)] border-r-2 border-black dark:border-blue-900 flex flex-col transition-all duration-200 ease-in-out -translate-x-full lg:translate-x-0">

            <!-- Full Sidebar Mode Content -->
            <div class="sidebar-full-content flex-1 flex flex-col overflow-hidden p-3.5">

                <!-- Header in Sidebar (Mobile Close Button) -->
                <div class="flex items-center justify-between pb-3 mb-3 border-b-2 border-dashed border-blue-500/30 lg:hidden">
                    <span class="font-pixel text-[11px] text-blue-500">QUEST DIRECTORY</span>
                    <button id="close-sidebar-btn" class="arcade-btn bg-red-600 text-white text-xs px-2 py-1 rounded">
                        ✖ CLOSE
                    </button>
                </div>

                <!-- Player Gamification Status Card -->
                <div class="arcade-card p-3 rounded-lg border-2 border-blue-500 bg-blue-50 dark:bg-blue-950/40 mb-3 shadow-[2px_2px_0px_#000]">
                    <div class="flex items-center gap-3 mb-2">
                        <!-- Pixel Character Avatar -->
                        <div class="w-10 h-10 rounded border-2 border-black bg-blue-600 flex items-center justify-center text-lg shrink-0 shadow-[2px_2px_0px_#000]">
                            🧙‍♂️
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-1">
                                <h3 id="player-rank-title" class="font-bold text-xs truncate text-blue-900 dark:text-cyan-300">
                                    {{ $stats['rank_badge'] ?? '🥉' }} {{ $stats['rank_title'] ?? 'Novice Scribe' }}
                                </h3>
                                <span id="player-level-badge" class="font-pixel text-[9px] bg-blue-600 text-white px-1.5 py-0.5 rounded border border-black shrink-0">
                                    LV.{{ $stats['level'] ?? 1 }}
                                </span>
                            </div>
                            <div class="text-[10px] font-mono text-gray-500 dark:text-gray-400 flex items-center justify-between">
                                <span id="player-mana-text">{{ $stats['mana'] ?? '50/100 MP' }}</span>
                                <span id="player-xp-text" class="text-amber-500 font-bold">{{ $stats['total_xp'] ?? 10 }} XP</span>
                            </div>
                        </div>
                    </div>

                    <!-- XP Progress Bar -->
                    <div class="w-full bg-black/20 dark:bg-black/60 h-2.5 rounded border border-black/40 overflow-hidden p-[1px]">
                        <div id="player-xp-bar"
                             class="h-full bg-gradient-to-r from-blue-500 to-cyan-400 rounded transition-all duration-300 shadow-[0_0_8px_rgba(6,182,212,0.6)]"
                             style="width: {{ $stats['progress_percent'] ?? 15 }}%;"></div>
                    </div>
                </div>

                <!-- New Note / Scroll Spawn Action Button -->
                <button id="new-note-btn"
                        class="arcade-btn w-full bg-blue-600 hover:bg-blue-500 text-white py-2 px-3 rounded text-xs font-mono mb-3 tracking-wider">
                    ⚡ + NEW SCROLL / NOTE
                </button>

                <!-- Search Input with Retro Arcade Filter Styling -->
                <div class="relative mb-3">
                    <input type="text"
                           id="search-notes-input"
                           placeholder="🔍 Search scrolls or lore..."
                           class="w-full bg-white dark:bg-black/40 border-2 border-black dark:border-blue-700 rounded px-2.5 py-1.5 text-xs font-mono focus:outline-none focus:border-cyan-400 shadow-[2px_2px_0px_#000]">
                </div>

                <!-- Category Pills Filter Bar -->
                <div class="flex items-center gap-1 overflow-x-auto pb-2 mb-2 text-[11px] font-mono scrollbar-none">
                    <button class="category-filter-btn px-2 py-0.5 rounded border border-black/40 bg-blue-600 text-white active-category whitespace-nowrap shadow-[1px_1px_0px_#000]"
                            data-category="all">
                        ALL
                    </button>
                    <button class="category-filter-btn px-2 py-0.5 rounded border border-black/40 bg-surface whitespace-nowrap hover:bg-blue-200 dark:hover:bg-blue-900 shadow-[1px_1px_0px_#000]"
                            data-category="quest">
                        ⚔️ QUESTS
                    </button>
                    <button class="category-filter-btn px-2 py-0.5 rounded border border-black/40 bg-surface whitespace-nowrap hover:bg-blue-200 dark:hover:bg-blue-900 shadow-[1px_1px_0px_#000]"
                            data-category="spellbook">
                        🔮 SPELLS
                    </button>
                    <button class="category-filter-btn px-2 py-0.5 rounded border border-black/40 bg-surface whitespace-nowrap hover:bg-blue-200 dark:hover:bg-blue-900 shadow-[1px_1px_0px_#000]"
                            data-category="strategy">
                        🛡️ RAIDS
                    </button>
                    <button class="category-filter-btn px-2 py-0.5 rounded border border-black/40 bg-surface whitespace-nowrap hover:bg-blue-200 dark:hover:bg-blue-900 shadow-[1px_1px_0px_#000]"
                            data-category="lore">
                        📜 LORE
                    </button>
                </div>

                <!-- Dynamic Note List Container -->
                <div class="flex-1 overflow-y-auto pr-1 space-y-1" id="notes-list">
                    <!-- Note items dynamically populated via app.js -->
                </div>

                <!-- Footer Summary of Database Matrix -->
                <div class="pt-2 mt-2 border-t border-black/10 dark:border-white/10 flex items-center justify-between text-[10px] font-mono opacity-70">
                    <span>💾 Storage: MySQL Text Chunks</span>
                    <span>⚡ 100% Dynamic</span>
                </div>
            </div>

            <!-- Collapsed Sidebar Mode Content (Icon only bar) -->
            <div class="sidebar-collapsed-content flex-col items-center py-4 gap-4 h-full">
                <button onclick="window.notenux.audio.playBlip(); document.getElementById('notenux-sidebar').classList.remove('collapsed');"
                        class="arcade-btn bg-blue-600 text-white p-2 rounded text-xs"
                        title="Expand Sidebar">
                    ⏩
                </button>
                <div class="w-8 h-8 rounded border-2 border-black bg-blue-600 flex items-center justify-center text-xs shadow-[1px_1px_0px_#000]"
                     title="Player Level {{ $stats['level'] ?? 1 }}">
                    🧙
                </div>
                <button onclick="window.notenux.createNewNote(true)"
                        class="arcade-btn bg-cyan-600 text-white p-2 rounded text-xs"
                        title="New Note">
                    +
                </button>
                <button onclick="window.notenux.openChunkInspector()"
                        class="arcade-btn bg-blue-800 text-cyan-300 p-2 rounded text-xs"
                        title="Inspect Database Chunks">
                    📦
                </button>
            </div>
        </aside>

        <!-- ============================================================================== -->
        <!-- MAIN PANEL & EDITABLE WORKSPACE -->
        <!-- ============================================================================== -->
        <main class="flex-1 flex flex-col overflow-y-auto p-3 sm:p-5 lg:p-6">
            <div class="max-w-5xl w-full mx-auto flex-1 flex flex-col space-y-3">

                <!-- Note Header Bar: Title, Category Badge, Status, Pin, Delete, Save -->
                <div class="arcade-card p-3 sm:p-4 rounded-xl flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 shadow-[3px_3px_0px_#000]">
                    <!-- Title Input -->
                    <div class="flex-1 min-w-0 flex items-center gap-2">
                        <span class="text-xl sm:text-2xl shrink-0">📜</span>
                        <input type="text"
                               id="note-title-input"
                               placeholder="Scroll Title..."
                               value="{{ $activeNote->title ?? 'Main Quest Scroll' }}"
                               class="w-full text-base sm:text-xl font-bold bg-transparent border-b-2 border-transparent hover:border-blue-400 focus:border-cyan-400 focus:outline-none transition-colors">
                    </div>

                    <!-- Actions (Pin, Delete, Save Indicator, Manual Save) -->
                    <div class="flex items-center gap-2 shrink-0 justify-end flex-wrap">
                        <span id="save-status-text" class="text-xs font-mono text-emerald-500 mr-1">
                            ✓ Synchronized
                        </span>

                        <button id="pin-note-btn"
                                class="arcade-btn bg-blue-600 hover:bg-blue-500 text-white px-2.5 py-1 rounded text-xs font-mono"
                                title="Toggle Pinned Note">
                            📍 <span class="hidden sm:inline">PIN</span>
                        </button>

                        <button id="delete-note-btn"
                                class="arcade-btn bg-rose-600 hover:bg-rose-500 text-white px-2.5 py-1 rounded text-xs font-mono"
                                title="Banish Scroll to the Void">
                            🗑️ <span class="hidden sm:inline">DELETE</span>
                        </button>

                        <button id="save-now-btn"
                                class="arcade-btn bg-emerald-600 hover:bg-emerald-500 text-white px-3 py-1 rounded text-xs font-mono font-bold tracking-wider"
                                title="Instantly inscribe and split into database chunks">
                            💾 INSCRIBE
                        </button>
                    </div>
                </div>

                <!-- Flexible Typography & Formatting Toolbar -->
                <div class="arcade-card p-2 sm:p-2.5 rounded-xl flex flex-wrap items-center gap-1.5 sm:gap-2 shadow-[2px_2px_0px_#000]">

                    <!-- Font Family Switcher -->
                    <div class="flex items-center gap-1 bg-surface-alt p-1 rounded border border-black/30 dark:border-blue-800">
                        <span class="text-[11px] font-mono font-bold px-1 hidden sm:inline">FONT:</span>
                        <select id="font-family-select"
                                class="bg-white dark:bg-black/50 border border-black/40 rounded px-2 py-0.5 text-xs font-mono focus:outline-none focus:border-cyan-400">
                            <option value="pixel">👾 Retro Pixel (Press Start 2P)</option>
                            <option value="arcade">🕹️ 8-Bit Arcade (VT323)</option>
                            <option value="mono">⚡ Cyber Mono (Fira Code)</option>
                            <option value="sans">📘 Modern Sans (Instrument Sans)</option>
                            <option value="serif">📜 Grimoire (Merriweather)</option>
                        </select>
                    </div>

                    <!-- Font Size Selector -->
                    <div class="flex items-center gap-1 bg-surface-alt p-1 rounded border border-black/30 dark:border-blue-800">
                        <span class="text-[11px] font-mono font-bold px-1 hidden sm:inline">SIZE:</span>
                        <select id="font-size-select"
                                class="bg-white dark:bg-black/50 border border-black/40 rounded px-1.5 py-0.5 text-xs font-mono focus:outline-none focus:border-cyan-400">
                            <option value="12px">12px</option>
                            <option value="14px">14px</option>
                            <option value="16px" selected>16px</option>
                            <option value="18px">18px</option>
                            <option value="20px">20px</option>
                            <option value="24px">24px</option>
                            <option value="28px">28px</option>
                            <option value="32px">32px</option>
                        </select>
                    </div>

                    <!-- Divider -->
                    <div class="h-5 w-[1px] bg-black/20 dark:bg-white/20 mx-1"></div>

                    <!-- Text Formatting Group -->
                    <div class="flex items-center gap-1">
                        <button data-command="bold" class="arcade-btn bg-surface hover:bg-blue-200 dark:hover:bg-blue-800 px-2 py-0.5 rounded text-xs font-bold" title="Bold (Ctrl+B)">B</button>
                        <button data-command="italic" class="arcade-btn bg-surface hover:bg-blue-200 dark:hover:bg-blue-800 px-2 py-0.5 rounded text-xs italic" title="Italic (Ctrl+I)">I</button>
                        <button data-command="underline" class="arcade-btn bg-surface hover:bg-blue-200 dark:hover:bg-blue-800 px-2 py-0.5 rounded text-xs underline" title="Underline (Ctrl+U)">U</button>
                        <button data-command="strikeThrough" class="arcade-btn bg-surface hover:bg-blue-200 dark:hover:bg-blue-800 px-2 py-0.5 rounded text-xs line-through" title="Strikethrough">S</button>
                    </div>

                    <!-- Headings -->
                    <div class="flex items-center gap-1">
                        <button data-command="formatBlock" data-value="h1" class="arcade-btn bg-surface hover:bg-blue-200 dark:hover:bg-blue-800 px-1.5 py-0.5 rounded text-xs font-bold" title="Heading 1">H1</button>
                        <button data-command="formatBlock" data-value="h2" class="arcade-btn bg-surface hover:bg-blue-200 dark:hover:bg-blue-800 px-1.5 py-0.5 rounded text-xs font-bold" title="Heading 2">H2</button>
                        <button data-command="formatBlock" data-value="h3" class="arcade-btn bg-surface hover:bg-blue-200 dark:hover:bg-blue-800 px-1.5 py-0.5 rounded text-xs font-bold" title="Heading 3">H3</button>
                        <button data-command="formatBlock" data-value="p" class="arcade-btn bg-surface hover:bg-blue-200 dark:hover:bg-blue-800 px-1.5 py-0.5 rounded text-xs font-bold" title="Paragraph">P</button>
                    </div>

                    <!-- Lists & Quotes -->
                    <div class="flex items-center gap-1">
                        <button data-command="insertUnorderedList" class="arcade-btn bg-surface hover:bg-blue-200 dark:hover:bg-blue-800 px-2 py-0.5 rounded text-xs" title="Bullet List">• List</button>
                        <button data-command="insertOrderedList" class="arcade-btn bg-surface hover:bg-blue-200 dark:hover:bg-blue-800 px-2 py-0.5 rounded text-xs" title="Numbered List">1. List</button>
                        <button data-command="formatBlock" data-value="blockquote" class="arcade-btn bg-surface hover:bg-blue-200 dark:hover:bg-blue-800 px-2 py-0.5 rounded text-xs" title="Quote Block">❝ Quote</button>
                        <button data-command="formatBlock" data-value="pre" class="arcade-btn bg-surface hover:bg-blue-200 dark:hover:bg-blue-800 px-2 py-0.5 rounded text-xs font-mono" title="Code Block">&lt;/&gt;</button>
                        <button data-command="insertHorizontalRule" class="arcade-btn bg-surface hover:bg-blue-200 dark:hover:bg-blue-800 px-2 py-0.5 rounded text-xs" title="Divider Line">—</button>
                    </div>

                    <!-- Text Alignment -->
                    <div class="flex items-center gap-1">
                        <button data-command="justifyLeft" class="arcade-btn bg-surface hover:bg-blue-200 dark:hover:bg-blue-800 px-1.5 py-0.5 rounded text-xs" title="Align Left">⫷</button>
                        <button data-command="justifyCenter" class="arcade-btn bg-surface hover:bg-blue-200 dark:hover:bg-blue-800 px-1.5 py-0.5 rounded text-xs" title="Align Center">☵</button>
                        <button data-command="justifyRight" class="arcade-btn bg-surface hover:bg-blue-200 dark:hover:bg-blue-800 px-1.5 py-0.5 rounded text-xs" title="Align Right">⫸</button>
                    </div>

                    <!-- Retro Color Palette Quick Swatches -->
                    <div class="flex items-center gap-1 bg-surface-alt p-1 rounded border border-black/30 dark:border-blue-800">
                        <span class="text-[10px] font-mono hidden xl:inline">COLOR:</span>
                        <button data-color="#38bdf8" data-color-type="foreColor" class="w-4 h-4 rounded-full bg-cyan-400 border border-black" title="Neon Cyan"></button>
                        <button data-color="#3b82f6" data-color-type="foreColor" class="w-4 h-4 rounded-full bg-blue-500 border border-black" title="Arcade Blue"></button>
                        <button data-color="#10b981" data-color-type="foreColor" class="w-4 h-4 rounded-full bg-emerald-500 border border-black" title="Emerald Glow"></button>
                        <button data-color="#f59e0b" data-color-type="foreColor" class="w-4 h-4 rounded-full bg-amber-500 border border-black" title="Pixel Gold"></button>
                        <button data-color="#ef4444" data-color-type="foreColor" class="w-4 h-4 rounded-full bg-rose-500 border border-black" title="Crimson"></button>
                        <button data-color="#f8fafc" data-color-type="foreColor" class="w-4 h-4 rounded-full bg-slate-100 border border-black" title="White"></button>
                    </div>

                    <!-- Clear formatting button -->
                    <button data-command="removeFormat" class="arcade-btn bg-surface hover:bg-red-200 dark:hover:bg-red-950 px-2 py-0.5 rounded text-xs font-mono" title="Clear Formatting">
                        🧹 CLEAR
                    </button>
                </div>

                <!-- Gamified Live Metrics & Quest Status Bar -->
                <div class="flex items-center justify-between gap-2 px-1 text-xs font-mono">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span id="word-count-badge" class="px-2 py-0.5 rounded border border-black/30 bg-blue-100 dark:bg-blue-900/40 font-bold">
                            0 Words
                        </span>
                        <span id="char-count-badge" class="px-2 py-0.5 rounded border border-black/30 bg-blue-100 dark:bg-blue-900/40">
                            0 Chars
                        </span>
                        <span id="reading-time-badge" class="px-2 py-0.5 rounded border border-black/30 bg-blue-100 dark:bg-blue-900/40">
                            ⏱️ 1m Read
                        </span>
                    </div>

                    <div class="flex items-center gap-2">
                        <span id="chunk-count-badge" class="px-2 py-0.5 rounded border border-black/30 bg-cyan-100 dark:bg-cyan-900/40 text-cyan-800 dark:text-cyan-300 font-bold">
                            📦 1 Chunks
                        </span>
                        <span class="text-[10px] opacity-75 hidden sm:inline">
                            ✨ +3 XP / Word
                        </span>
                    </div>
                </div>

                <!-- Editable Content Area (The Core Canvas) -->
                <div class="arcade-card flex-1 p-4 sm:p-6 rounded-xl overflow-y-auto relative shadow-[4px_4px_0px_#000]">
                    <div id="editor-content"
                         contenteditable="true"
                         spellcheck="false"
                         class="notenux-editor prose dark:prose-invert max-w-none focus:outline-none"
                         style="font-family: 'Press Start 2P', monospace; font-size: 16px;">
                        {!! $activeNote->content ?? '<h2>⚔️ Mission Briefing: Act I</h2><p>Begin scribing your thoughts...</p>' !!}
                    </div>
                </div>

                <!-- Bottom Telemetry Bar -->
                <div class="arcade-card p-2 rounded-lg flex items-center justify-between text-[11px] font-mono opacity-80 shadow-[2px_2px_0px_#000]">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span>Terminal Status: ONLINE</span>
                        <span class="hidden sm:inline">| DB: <code class="text-cyan-500">notenux.note_chunks</code></span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span>Chunk Identity: UUID / ULID</span>
                        <span class="hidden sm:inline">| SHA-256 Checksummed</span>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- ============================================================================== -->
    <!-- DATABASE CHUNK INSPECTOR MODAL -->
    <!-- ============================================================================== -->
    <div id="chunk-inspector-modal"
         class="fixed inset-0 z-50 bg-black/70 backdrop-blur-xs flex items-center justify-center p-3 sm:p-6 hidden">
        <div class="arcade-card w-full max-w-2xl bg-[var(--bg-surface)] rounded-xl border-4 border-black dark:border-cyan-500 shadow-[8px_8px_0px_#000] flex flex-col max-h-[85vh] overflow-hidden">

            <!-- Modal Header -->
            <div class="p-3.5 sm:p-4 border-b-2 border-black dark:border-blue-800 bg-blue-600 text-white flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="text-xl">📦</span>
                    <div>
                        <h3 class="font-pixel text-xs sm:text-sm tracking-wider">DATABASE CHUNK INSPECTOR</h3>
                        <p class="text-[10px] font-mono text-cyan-200">Identity-backed chunked text storage telemetry</p>
                    </div>
                </div>
                <button id="chunk-modal-close" class="arcade-btn bg-black text-white hover:bg-gray-800 px-2.5 py-1 rounded text-xs font-mono">
                    ✖ ESC
                </button>
            </div>

            <!-- Modal Subheader Stats -->
            <div class="px-4 py-2 bg-surface-alt border-b border-black/10 dark:border-white/10 flex items-center justify-between font-mono text-xs">
                <span id="chunk-modal-count" class="font-bold text-blue-600 dark:text-cyan-400">Loading chunks...</span>
                <span id="chunk-modal-bytes" class="opacity-75">0 Bytes</span>
            </div>

            <!-- Modal Body (Chunks List) -->
            <div id="chunk-modal-content" class="p-4 overflow-y-auto flex-1 space-y-2">
                <!-- Dynamically loaded via app.js -->
            </div>

            <!-- Modal Footer -->
            <div class="p-3 border-t-2 border-black dark:border-blue-800 bg-surface-alt flex items-center justify-between text-[11px] font-mono">
                <span class="opacity-80">Each chunk has a unique database key: <code class="text-cyan-600 dark:text-cyan-400">chunk_uid</code></span>
                <button onclick="window.notenux.closeChunkInspector()" class="arcade-btn bg-blue-600 text-white px-3 py-1 rounded text-xs">
                    GOT IT
                </button>
            </div>
        </div>
    </div>

    <!-- Hidden Server Initial Data for Seamless Hydration -->
    <script id="initial-notes-data" type="application/json">
        {!! json_encode($notes) !!}
    </script>
    <input type="hidden" id="initial-active-note-id" value="{{ $activeNote->id ?? '' }}">

</body>
</html>
