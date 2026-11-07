<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Notenux') }} - Retro Gamified Notes Studio & Memory Shard Matrix</title>

    <!-- Font Awesome 6 Free CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <!-- Google Fonts for Retro Aesthetics -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;600&family=Instrument+Sans:wght@400;500;600;700&family=Merriweather:ital,wght@0,300;0,400;0,700;1,300&family=Press+Start+2P&family=VT323&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <script>
        (function() {
            const savedTheme = localStorage.getItem('notenux_theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            if (savedTheme === 'dark') {
                document.documentElement.classList.add('dark');
                document.documentElement.setAttribute('data-theme', 'dark');
            } else {
                document.documentElement.classList.remove('dark');
                document.documentElement.setAttribute('data-theme', 'light');
            }
        })();
    </script>
</head>
<body class="retro-crt-screen bg-[var(--bg-main)] text-[var(--text-main)] min-h-screen font-sans flex flex-col antialiased selection:bg-blue-600 selection:text-white transition-colors duration-200">

    <!-- Top Navigation Header -->
    <header class="sticky top-0 w-full bg-[var(--bg-surface)] border-b-2 border-black dark:border-blue-900 shadow-[0_4px_0_rgba(0,0,0,0.25)] px-4 sm:px-6 py-3 z-40">
        <div class="max-w-7xl mx-auto flex items-center justify-between gap-4">

            <!-- Brand Logo -->
            <a href="{{ route('landing') }}" class="flex items-center gap-2 group transition-transform active:translate-y-0.5">
                <i class="fa-solid fa-gamepad text-2xl sm:text-3xl text-blue-600 dark:text-cyan-400 group-hover:rotate-12 transition-transform"></i>
                <div>
                    <div class="flex items-center gap-1.5">
                        <span class="font-pixel text-sm sm:text-lg text-blue-600 dark:text-cyan-400 tracking-wider">NOTENUX</span>
                        <span class="text-[9px] font-mono bg-blue-900/20 text-blue-600 dark:text-cyan-300 px-1.5 py-0.5 rounded border border-blue-500/40 font-bold uppercase">v3.0</span>
                    </div>
                    <p class="text-[9px] font-mono text-slate-500 dark:text-slate-400 hidden sm:block">Retro Gamified Note Studio</p>
                </div>
            </a>

            <!-- Navigation Links (Desktop) -->
            <nav class="hidden md:flex items-center gap-6 font-mono text-xs font-bold">
                <a href="#fitur" class="hover:text-blue-600 dark:hover:text-cyan-400 transition-colors">FITUR</a>
                <a href="#playground" class="hover:text-blue-600 dark:hover:text-cyan-400 transition-colors">DEMO INTERAKTIF</a>
                <a href="#arsitektur" class="hover:text-blue-600 dark:hover:text-cyan-400 transition-colors">CHUNK MATRIX</a>
                <a href="#faq" class="hover:text-blue-600 dark:hover:text-cyan-400 transition-colors">FAQ</a>
            </nav>

            <!-- Right Controls & Authentication Actions -->
            <div class="flex items-center gap-2 sm:gap-3">
                <!-- Sound Toggle -->
                <button id="sound-toggle-btn"
                        class="arcade-btn bg-blue-100 hover:bg-blue-200 dark:bg-blue-950 dark:hover:bg-blue-900 text-blue-900 dark:text-blue-300 px-2.5 py-1.5 rounded text-xs font-mono"
                        title="Toggle 8-bit Audio SFX">
                    <i class="fa-solid fa-volume-high text-blue-600 dark:text-cyan-400 mr-1"></i> <span class="hidden xl:inline">SFX</span>
                </button>

                <!-- CRT Scanline Toggle -->
                <button id="crt-toggle-btn"
                        class="arcade-btn bg-purple-100 hover:bg-purple-200 dark:bg-purple-900/40 dark:hover:bg-purple-800/60 text-purple-900 dark:text-purple-300 px-2.5 py-1.5 rounded text-xs font-mono hidden sm:inline-flex"
                        title="Toggle Retro CRT Scanlines">
                    <i class="fa-solid fa-tv mr-1 text-purple-600 dark:text-purple-400"></i> <span class="hidden xl:inline">CRT</span>
                </button>

                <!-- Theme Toggle Button -->
                <button id="theme-toggle-btn"
                        class="arcade-btn bg-amber-300 hover:bg-amber-400 dark:bg-blue-900 dark:hover:bg-blue-800 text-black dark:text-cyan-300 px-2.5 py-1.5 rounded text-xs font-mono"
                        title="Toggle Light / Dark Mode">
                    <i class="fa-solid fa-moon text-cyan-300 mr-1.5"></i> <span class="hidden sm:inline">TEMA</span>
                </button>

                <!-- Auth State -->
                @auth
                    <!-- User is logged in -->
                    <div class="flex items-center gap-2">
                        <div class="hidden sm:flex items-center gap-1.5 px-2.5 py-1 rounded-lg border border-black dark:border-blue-700 bg-[var(--bg-surface-alt)] text-xs font-mono">
                            <i class="fa-solid fa-user-ninja text-emerald-500"></i>
                            <span class="font-bold truncate max-w-[120px]">{{ auth()->user()->name }}</span>
                        </div>

                        <a href="{{ route('studio') }}"
                           id="nav-studio-btn"
                           class="arcade-btn bg-blue-600 hover:bg-blue-500 text-white px-3 sm:px-4 py-1.5 rounded text-xs font-mono font-bold tracking-wider">
                            <i class="fa-solid fa-terminal mr-1.5"></i> STUDIO
                        </a>

                        <form method="POST" action="{{ route('logout') }}" class="inline">
                            @csrf
                            <button type="submit"
                                    id="nav-logout-btn"
                                    class="arcade-btn bg-red-600 hover:bg-red-500 text-white px-2.5 py-1.5 rounded text-xs font-mono"
                                    title="Logout dari akun">
                                <i class="fa-solid fa-right-from-bracket"></i>
                            </button>
                        </form>
                    </div>
                @else
                    <!-- User is guest -->
                    <a href="{{ route('login') }}"
                       id="nav-login-btn"
                       class="arcade-btn bg-slate-200 hover:bg-slate-300 dark:bg-slate-800 dark:hover:bg-slate-700 text-xs font-mono px-3 py-1.5 rounded">
                        <i class="fa-solid fa-right-to-bracket mr-1"></i> <span>MASUK</span>
                    </a>

                    <a href="{{ route('register') }}"
                       id="nav-register-btn"
                       class="arcade-btn bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-mono px-3 py-1.5 rounded hidden sm:inline-flex">
                        <i class="fa-solid fa-user-plus mr-1"></i> <span>REGISTER</span>
                    </a>

                    <a href="{{ route('studio') }}"
                       id="nav-studio-guest-btn"
                       class="arcade-btn bg-blue-600 hover:bg-blue-500 text-white px-3 sm:px-4 py-1.5 rounded text-xs font-mono font-bold tracking-wider">
                        <i class="fa-solid fa-terminal mr-1.5"></i> STUDIO
                    </a>
                @endauth
            </div>

        </div>
    </header>

    <!-- Main Content Area -->
    <main class="flex-1">

        <!-- ============================================================================== -->
        <!-- 1. HERO SECTION -->
        <!-- ============================================================================== -->
        <section class="relative overflow-hidden py-12 sm:py-20 lg:py-24 px-4 sm:px-6">
            <!-- Retro Cyberpunk Background Accents -->
            <div class="absolute -top-24 left-1/2 -translate-x-1/2 w-[600px] h-[350px] bg-blue-500/10 dark:bg-cyan-500/10 blur-3xl pointer-events-none rounded-full"></div>

            <div class="max-w-5xl mx-auto text-center space-y-6 relative z-10">

                <!-- Badge -->
                <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full border-2 border-black dark:border-cyan-400/50 bg-blue-100 dark:bg-blue-950/70 text-blue-900 dark:text-cyan-300 font-pixel text-[10px] tracking-wider shadow-[3px_3px_0px_#000]">
                    <i class="fa-solid fa-bolt text-amber-500 animate-pulse"></i>
                    <span>RETRO GAMIFIED NOTE STUDIO & MARKDOWN MATRIX</span>
                </div>

                <!-- Main Headline -->
                <h1 class="text-3xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight text-slate-900 dark:text-white leading-tight">
                    Abadikan Pemikiran. <br class="hidden sm:block">
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-600 via-cyan-500 to-indigo-600 dark:from-cyan-300 dark:via-blue-400 dark:to-purple-400">
                        Tingkatkan Level Scribe.
                    </span>
                </h1>

                <!-- Subheadline -->
                <p class="max-w-2xl mx-auto text-sm sm:text-base text-slate-600 dark:text-slate-300 font-mono leading-relaxed">
                    Bukan sekadar aplikasi catatan biasa. Notenux menggabungkan kenyamanan editor Markdown multi-font, integritas penyimpanan chunk kriptografis, dan sistem leveling RPG gamifikasi yang membuat aktivitas menulismu adiktif.
                </p>

                <!-- Hero Action Buttons -->
                <div class="pt-4 flex flex-wrap items-center justify-center gap-3 sm:gap-4 font-mono">
                    <!-- Primary CTA leading to /studio -->
                    <a href="{{ route('studio') }}"
                       id="hero-studio-cta"
                       class="arcade-btn px-6 py-3.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-pixel text-xs sm:text-sm tracking-wider shadow-[5px_5px_0px_#000] active:translate-y-1 transition-all">
                        <i class="fa-solid fa-terminal mr-2 text-cyan-300"></i>
                        <span>BUKA STUDIO</span>
                    </a>

                    @guest
                        <a href="{{ route('register') }}"
                           id="hero-register-cta"
                           class="arcade-btn px-6 py-3.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs sm:text-sm tracking-wider shadow-[5px_5px_0px_#000] active:translate-y-1 transition-all">
                            <i class="fa-solid fa-user-plus mr-2"></i>
                            <span>DAFTAR SCRIBE GRATIS</span>
                        </a>

                        <a href="{{ route('login') }}"
                           id="hero-login-cta"
                           class="arcade-btn px-5 py-3.5 rounded-xl bg-[var(--bg-surface)] hover:bg-slate-200 dark:hover:bg-blue-900/40 text-[var(--text-main)] font-bold text-xs sm:text-sm tracking-wider shadow-[4px_4px_0px_#000] active:translate-y-1 transition-all">
                            <i class="fa-solid fa-right-to-bracket mr-1.5 text-blue-500"></i>
                            <span>MASUK</span>
                        </a>
                    @else
                        <a href="{{ route('categories.index') }}"
                           class="arcade-btn px-5 py-3.5 rounded-xl bg-[var(--bg-surface)] hover:bg-slate-200 dark:hover:bg-blue-900/40 text-[var(--text-main)] font-bold text-xs sm:text-sm tracking-wider shadow-[4px_4px_0px_#000] active:translate-y-1 transition-all">
                            <i class="fa-solid fa-folder-open mr-1.5 text-cyan-400"></i>
                            <span>KELOLA KATEGORI</span>
                        </a>
                    @endguest
                </div>

                <!-- Hero Metrics Telemetry Strip -->
                <div class="pt-8 max-w-4xl mx-auto grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4 font-mono text-left">
                    <div class="arcade-card p-3 rounded-lg border-2 border-black dark:border-blue-900 bg-[var(--bg-surface)] shadow-[3px_3px_0px_#000]">
                        <div class="flex items-center gap-2 text-blue-600 dark:text-cyan-400 text-xs font-bold">
                            <i class="fa-solid fa-scroll"></i>
                            <span>MANUSCRIPTS</span>
                        </div>
                        <div class="mt-1 font-pixel text-lg sm:text-xl text-slate-900 dark:text-white">{{ $noteCount }}+</div>
                        <p class="text-[10px] text-slate-500 dark:text-slate-400">Catatan tersimpan</p>
                    </div>

                    <div class="arcade-card p-3 rounded-lg border-2 border-black dark:border-blue-900 bg-[var(--bg-surface)] shadow-[3px_3px_0px_#000]">
                        <div class="flex items-center gap-2 text-cyan-600 dark:text-cyan-300 text-xs font-bold">
                            <i class="fa-solid fa-cubes"></i>
                            <span>DB SHARDS</span>
                        </div>
                        <div class="mt-1 font-pixel text-lg sm:text-xl text-slate-900 dark:text-white">{{ $chunkCount }}+</div>
                        <p class="text-[10px] text-slate-500 dark:text-slate-400">Chunk terpartisi aman</p>
                    </div>

                    <div class="arcade-card p-3 rounded-lg border-2 border-black dark:border-blue-900 bg-[var(--bg-surface)] shadow-[3px_3px_0px_#000]">
                        <div class="flex items-center gap-2 text-amber-600 dark:text-yellow-400 text-xs font-bold">
                            <i class="fa-solid fa-award"></i>
                            <span>RPG LEVELING</span>
                        </div>
                        <div class="mt-1 font-pixel text-lg sm:text-xl text-slate-900 dark:text-white">LV.50+</div>
                        <p class="text-[10px] text-slate-500 dark:text-slate-400">XP & Mana Progress</p>
                    </div>

                    <div class="arcade-card p-3 rounded-lg border-2 border-black dark:border-blue-900 bg-[var(--bg-surface)] shadow-[3px_3px_0px_#000]">
                        <div class="flex items-center gap-2 text-purple-600 dark:text-purple-400 text-xs font-bold">
                            <i class="fa-solid fa-wand-magic-sparkles"></i>
                            <span>AI SPELLCASTER</span>
                        </div>
                        <div class="mt-1 font-pixel text-lg sm:text-xl text-slate-900 dark:text-white">100%</div>
                        <p class="text-[10px] text-slate-500 dark:text-slate-400">AI Assistant Ready</p>
                    </div>
                </div>

            </div>
        </section>

        <!-- ============================================================================== -->
        <!-- 2. INTERACTIVE LIVE PLAYGROUND / DEMO SECTION -->
        <!-- ============================================================================== -->
        <section id="playground" class="py-12 sm:py-16 px-4 sm:px-6 bg-[var(--bg-surface-alt)] border-y-2 border-black dark:border-blue-900">
            <div class="max-w-5xl mx-auto space-y-6">

                <!-- Section Header -->
                <div class="text-center space-y-2">
                    <div class="inline-flex items-center gap-1.5 px-3 py-0.5 rounded border border-blue-500/40 bg-blue-500/10 text-blue-600 dark:text-cyan-400 font-pixel text-[10px]">
                        <i class="fa-solid fa-gamepad"></i> INTERACTIVE PLAYGROUND
                    </div>
                    <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight">Coba Langsung Sensasi Menulis di Notenux</h2>
                    <p class="text-xs sm:text-sm font-mono text-slate-600 dark:text-slate-400 max-w-xl mx-auto">
                        Ubah tipografi font, ketik catatan, rasakan simulasi kenaikan XP secara real-time, dan lihat preview Markdown!
                    </p>
                </div>

                <!-- Interactive Playground Console -->
                <div class="arcade-card rounded-2xl border-2 border-black dark:border-blue-700 bg-[var(--bg-surface)] shadow-[8px_8px_0px_#000] overflow-hidden">

                    <!-- Console Top Bar -->
                    <div class="px-4 py-3 border-b-2 border-black dark:border-blue-900 bg-[var(--bg-surface-alt)] flex flex-wrap items-center justify-between gap-3">
                        <!-- Font Selector Switches -->
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <span class="text-[10px] font-mono font-bold mr-1 text-slate-600 dark:text-slate-400">FONT:</span>
                            <button type="button" class="demo-font-btn arcade-btn bg-blue-600 text-white text-[10px] font-mono px-2 py-1 rounded" data-font="pixel">
                                [Pixel]
                            </button>
                            <button type="button" class="demo-font-btn arcade-btn bg-[var(--bg-surface)] text-[var(--text-main)] text-[10px] font-mono px-2 py-1 rounded" data-font="arcade">
                                [Arcade]
                            </button>
                            <button type="button" class="demo-font-btn arcade-btn bg-[var(--bg-surface)] text-[var(--text-main)] text-[10px] font-mono px-2 py-1 rounded" data-font="mono">
                                [Fira Code]
                            </button>
                            <button type="button" class="demo-font-btn arcade-btn bg-[var(--bg-surface)] text-[var(--text-main)] text-[10px] font-mono px-2 py-1 rounded" data-font="sans">
                                [Sans]
                            </button>
                            <button type="button" class="demo-font-btn arcade-btn bg-[var(--bg-surface)] text-[var(--text-main)] text-[10px] font-mono px-2 py-1 rounded" data-font="serif">
                                [Serif]
                            </button>
                        </div>

                        <!-- Template Quick-Insert Buttons -->
                        <div class="flex items-center gap-1.5 text-xs font-mono">
                            <span class="text-[10px] font-bold text-slate-600 dark:text-slate-400 mr-1 hidden sm:inline">TEMPLATE:</span>
                            <button type="button" id="template-quest-btn" class="arcade-btn bg-amber-400 hover:bg-amber-300 text-black text-[10px] font-mono px-2 py-1 rounded" title="Muat template Quest">
                                ⚔️ Quest
                            </button>
                            <button type="button" id="template-code-btn" class="arcade-btn bg-cyan-600 hover:bg-cyan-500 text-white text-[10px] font-mono px-2 py-1 rounded" title="Muat template Code">
                                💻 Code Shard
                            </button>
                            <button type="button" id="toggle-preview-btn" class="arcade-btn bg-purple-600 hover:bg-purple-500 text-white text-[10px] font-mono px-2 py-1 rounded" title="Toggle Preview">
                                👁️ Preview
                            </button>
                        </div>
                    </div>

                    <!-- Telemetry Stats Simulation Bar -->
                    <div class="px-4 py-2 border-b-2 border-dashed border-black/10 dark:border-white/10 flex flex-wrap items-center justify-between gap-3 text-xs font-mono">
                        <div class="flex items-center gap-3">
                            <span class="font-pixel text-[10px] text-blue-600 dark:text-cyan-400" id="demo-level-display">LV. 1 NOVICE</span>
                            <div class="w-32 bg-black/20 dark:bg-black/60 h-2 rounded border border-black/30 overflow-hidden">
                                <div id="demo-xp-bar" class="h-full bg-gradient-to-r from-blue-500 to-cyan-400 transition-all duration-200" style="width: 25%;"></div>
                            </div>
                            <span class="text-[10px] font-bold text-amber-500" id="demo-xp-text">25 / 100 XP</span>
                        </div>

                        <div class="flex items-center gap-3 text-[11px] text-slate-600 dark:text-slate-400">
                            <span><strong id="demo-word-count">24</strong> kata</span>
                            <span>&bull;</span>
                            <span><strong id="demo-char-count">142</strong> karakter</span>
                            <span>&bull;</span>
                            <span class="text-emerald-500 font-bold"><i class="fa-solid fa-circle-check"></i> Auto-Synced</span>
                        </div>
                    </div>

                    <!-- Editor Workspace (Split / Interactive Area) -->
                    <div class="p-4 sm:p-6 min-h-[220px]">
                        <!-- Interactive Editor Box -->
                        <div id="demo-editor-container">
                            <textarea id="demo-editor-input"
                                      rows="7"
                                      class="w-full bg-transparent border-0 outline-none resize-none font-pixel text-xs sm:text-sm leading-relaxed text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:ring-0"
                                      placeholder="Ketik sesuatu di sini untuk merasakan simulasi level XP dan font retro..."># 📜 Arcane Grimoire: Quest Chrono-Shard

Venture into the neon dungeon of Notenux.
- [x] Scribe initial thoughts
- [x] Slice into cryptographic memory shards
- [ ] Achieve Level 50 Archmage status!

Ketuk tombol template di atas atau ketik apa saja di sini!</textarea>
                        </div>

                        <!-- Rendered Preview Box (Hidden by default, toggled with Preview button) -->
                        <div id="demo-preview-container" class="hidden p-4 rounded-lg border-2 border-dashed border-blue-500/40 bg-[var(--bg-main)] font-sans text-sm space-y-2"></div>
                    </div>

                    <!-- Playground Footer CTA -->
                    <div class="px-4 py-3 bg-[var(--bg-surface-alt)] border-t-2 border-black dark:border-blue-900 flex flex-wrap items-center justify-between gap-3 text-xs font-mono">
                        <span class="text-slate-600 dark:text-slate-400">
                            <i class="fa-solid fa-lightbulb text-amber-400 mr-1"></i> Ini adalah simulasi mini. Studio lengkap memiliki AI assistant, chunk manager, export & import!
                        </span>
                        <a href="{{ route('studio') }}" class="arcade-btn bg-blue-600 hover:bg-blue-500 text-white text-xs font-mono px-3.5 py-1.5 rounded font-bold">
                            BUKA STUDIO LENGKAP &rarr;
                        </a>
                    </div>

                </div>

            </div>
        </section>

        <!-- ============================================================================== -->
        <!-- 3. FEATURE HIGHLIGHTS (ARCADE ARSENAL) -->
        <!-- ============================================================================== -->
        <section id="fitur" class="py-16 sm:py-20 px-4 sm:px-6">
            <div class="max-w-6xl mx-auto space-y-10">

                <div class="text-center space-y-2">
                    <div class="inline-flex items-center gap-1.5 px-3 py-0.5 rounded border border-blue-500/40 bg-blue-500/10 text-blue-600 dark:text-cyan-400 font-pixel text-[10px]">
                        <i class="fa-solid fa-shield-halved"></i> ARSENAL FITUR
                    </div>
                    <h2 class="text-2xl sm:text-4xl font-extrabold tracking-tight">Teknologi Modern dalam Sentuhan Retro</h2>
                    <p class="text-xs sm:text-sm font-mono text-slate-600 dark:text-slate-400 max-w-2xl mx-auto">
                        Dibangun dengan arsitektur tangguh untuk penulis, programmer, dan pemikir yang mengutamakan kecepatan dan keamanan data.
                    </p>
                </div>

                <!-- 6 Feature Cards Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 font-mono">

                    <!-- Card 1: Chunk Matrix -->
                    <div class="arcade-card p-6 rounded-xl border-2 border-black dark:border-blue-900 bg-[var(--bg-surface)] shadow-[5px_5px_0px_#000] space-y-3 hover:-translate-y-1 transition-transform">
                        <div class="w-12 h-12 rounded-lg bg-blue-600 text-white border-2 border-black shadow-[2px_2px_0px_#000] flex items-center justify-center text-xl">
                            <i class="fa-solid fa-cubes"></i>
                        </div>
                        <h3 class="font-pixel text-xs sm:text-sm text-blue-950 dark:text-cyan-300">CHUNK MATRIX ENGINE</h3>
                        <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                            Setiap dokumen dipartisi menjadi shard independen dengan SHA-256 hash checksum. Tidak ada lagi risiko dokumen raksasa rusak atau hilang.
                        </p>
                    </div>

                    <!-- Card 2: RPG Gamification -->
                    <div class="arcade-card p-6 rounded-xl border-2 border-black dark:border-blue-900 bg-[var(--bg-surface)] shadow-[5px_5px_0px_#000] space-y-3 hover:-translate-y-1 transition-transform">
                        <div class="w-12 h-12 rounded-lg bg-amber-500 text-black border-2 border-black shadow-[2px_2px_0px_#000] flex items-center justify-center text-xl">
                            <i class="fa-solid fa-hat-wizard"></i>
                        </div>
                        <h3 class="font-pixel text-xs sm:text-sm text-blue-950 dark:text-cyan-300">RPG LEVELING & XP</h3>
                        <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                            Raih XP dan tingkatkan rank Scribe kamu mulai dari Novice hingga Mythic Chrono-Scribe seiring bertambahnya produktivitas menulismu.
                        </p>
                    </div>

                    <!-- Card 3: AI Spellcaster -->
                    <div class="arcade-card p-6 rounded-xl border-2 border-black dark:border-blue-900 bg-[var(--bg-surface)] shadow-[5px_5px_0px_#000] space-y-3 hover:-translate-y-1 transition-transform">
                        <div class="w-12 h-12 rounded-lg bg-purple-600 text-white border-2 border-black shadow-[2px_2px_0px_#000] flex items-center justify-center text-xl">
                            <i class="fa-solid fa-wand-magic-sparkles"></i>
                        </div>
                        <h3 class="font-pixel text-xs sm:text-sm text-blue-950 dark:text-cyan-300">AI SPELLCASTER ASSISTANT</h3>
                        <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                            Gunakan mantra AI terintegrasi untuk meringkas, memoles tata bahasa, menyusun outline, atau memperluas ide catatan dalam hitungan detik.
                        </p>
                    </div>

                    <!-- Card 4: Multi-Font Typography -->
                    <div class="arcade-card p-6 rounded-xl border-2 border-black dark:border-blue-900 bg-[var(--bg-surface)] shadow-[5px_5px_0px_#000] space-y-3 hover:-translate-y-1 transition-transform">
                        <div class="w-12 h-12 rounded-lg bg-cyan-600 text-white border-2 border-black shadow-[2px_2px_0px_#000] flex items-center justify-center text-xl">
                            <i class="fa-solid fa-font"></i>
                        </div>
                        <h3 class="font-pixel text-xs sm:text-sm text-blue-950 dark:text-cyan-300">MULTI-FONT RETRO STUDIO</h3>
                        <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                            Ganti atmosfer membaca dengan font Pixel 8-bit, VT323 Arcade, Fira Code Programmer, Instrument Sans, dan Merriweather Serif.
                        </p>
                    </div>

                    <!-- Card 5: Dark and Light Themes -->
                    <div class="arcade-card p-6 rounded-xl border-2 border-black dark:border-blue-900 bg-[var(--bg-surface)] shadow-[5px_5px_0px_#000] space-y-3 hover:-translate-y-1 transition-transform">
                        <div class="w-12 h-12 rounded-lg bg-slate-800 text-cyan-300 border-2 border-black shadow-[2px_2px_0px_#000] flex items-center justify-center text-xl">
                            <i class="fa-solid fa-circle-half-stroke"></i>
                        </div>
                        <h3 class="font-pixel text-xs sm:text-sm text-blue-950 dark:text-cyan-300">DUAL THEME & CRT FX</h3>
                        <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                            Pilihan mode Day (Arcade Light) atau Night (Cyberpunk Dark) dengan shader garis CRT scanlines yang dapat dinonaktifkan kapan saja.
                        </p>
                    </div>

                    <!-- Card 6: Universal Export / Import -->
                    <div class="arcade-card p-6 rounded-xl border-2 border-black dark:border-blue-900 bg-[var(--bg-surface)] shadow-[5px_5px_0px_#000] space-y-3 hover:-translate-y-1 transition-transform">
                        <div class="w-12 h-12 rounded-lg bg-emerald-600 text-white border-2 border-black shadow-[2px_2px_0px_#000] flex items-center justify-center text-xl">
                            <i class="fa-solid fa-file-export"></i>
                        </div>
                        <h3 class="font-pixel text-xs sm:text-sm text-blue-950 dark:text-cyan-300">UNIVERSAL ZERO LOCK-IN</h3>
                        <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                            Data adalah milikmu seutuhnya. Ekspor catatanmu ke format Markdown (.md), HTML murni, maupun cadangan lengkap berkas JSON.
                        </p>
                    </div>

                </div>

            </div>
        </section>

        <!-- ============================================================================== -->
        <!-- 4. CHUNK MATRIX ARCHITECTURE EXPLAINER -->
        <!-- ============================================================================== -->
        <section id="arsitektur" class="py-16 sm:py-20 px-4 sm:px-6 bg-[var(--bg-surface-alt)] border-t-2 border-black dark:border-blue-900 font-mono">
            <div class="max-w-5xl mx-auto space-y-8">

                <div class="text-center space-y-2">
                    <div class="inline-flex items-center gap-1.5 px-3 py-0.5 rounded border border-blue-500/40 bg-blue-500/10 text-blue-600 dark:text-cyan-400 font-pixel text-[10px]">
                        <i class="fa-solid fa-network-wired"></i> UNDER THE HOOD
                    </div>
                    <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight">Arsitektur Shard Database Notenux</h2>
                    <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-400 max-w-xl mx-auto">
                        Bagaimana Notenux menjaga integritas data catatanmu tanpa membebani browser dan memori database.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-xs">
                    <div class="arcade-card p-5 rounded-xl border-2 border-black dark:border-blue-800 bg-[var(--bg-surface)] shadow-[4px_4px_0px_#000] space-y-2">
                        <div class="text-blue-600 dark:text-cyan-400 font-bold flex items-center gap-2">
                            <span class="w-6 h-6 rounded bg-blue-600 text-white flex items-center justify-center text-[10px] font-pixel">01</span>
                            <span>Semantic Sharding</span>
                        </div>
                        <p class="text-slate-600 dark:text-slate-300 leading-relaxed">
                            Teks catatan dipecah secara cerdas berdasarkan blok paragraf, tabel, atau heading menjadi unit terkontrol (`NoteChunk`).
                        </p>
                    </div>

                    <div class="arcade-card p-5 rounded-xl border-2 border-black dark:border-blue-800 bg-[var(--bg-surface)] shadow-[4px_4px_0px_#000] space-y-2">
                        <div class="text-emerald-600 dark:text-emerald-400 font-bold flex items-center gap-2">
                            <span class="w-6 h-6 rounded bg-emerald-600 text-white flex items-center justify-center text-[10px] font-pixel">02</span>
                            <span>SHA-256 Checksums</span>
                        </div>
                        <p class="text-slate-600 dark:text-slate-300 leading-relaxed">
                            Tiap shard memiliki sidik jari kriptografis. Fungsi verifikasi mendeteksi manipulasi data atau desinkronisasi seketika.
                        </p>
                    </div>

                    <div class="arcade-card p-5 rounded-xl border-2 border-black dark:border-blue-800 bg-[var(--bg-surface)] shadow-[4px_4px_0px_#000] space-y-2">
                        <div class="text-purple-600 dark:text-purple-400 font-bold flex items-center gap-2">
                            <span class="w-6 h-6 rounded bg-purple-600 text-white flex items-center justify-center text-[10px] font-pixel">03</span>
                            <span>Zero Memory Bloat</span>
                        </div>
                        <p class="text-slate-600 dark:text-slate-300 leading-relaxed">
                            Daftar catatan memuat metadata ringan tanpa membebani memori, hanya memuat teks penuh saat dokumen aktif dibuka.
                        </p>
                    </div>
                </div>

            </div>
        </section>

        <!-- ============================================================================== -->
        <!-- 5. INTERACTIVE FAQ ACCORDION -->
        <!-- ============================================================================== -->
        <section id="faq" class="py-16 sm:py-20 px-4 sm:px-6">
            <div class="max-w-4xl mx-auto space-y-8 font-mono">

                <div class="text-center space-y-2">
                    <div class="inline-flex items-center gap-1.5 px-3 py-0.5 rounded border border-blue-500/40 bg-blue-500/10 text-blue-600 dark:text-cyan-400 font-pixel text-[10px]">
                        <i class="fa-solid fa-circle-question"></i> PERTANYAAN UMUM
                    </div>
                    <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight">Kerap Ditanyakan Scribe</h2>
                </div>

                <div class="space-y-3">
                    <!-- FAQ 1 -->
                    <div class="faq-item arcade-card rounded-xl border-2 border-black dark:border-blue-900 bg-[var(--bg-surface)] shadow-[3px_3px_0px_#000] overflow-hidden">
                        <button type="button" class="faq-trigger w-full px-5 py-4 text-left font-bold text-xs sm:text-sm flex items-center justify-between gap-4 cursor-pointer">
                            <span class="flex items-center gap-2 text-blue-950 dark:text-cyan-300">
                                <i class="fa-solid fa-lock text-blue-500"></i> Mengapa harus login untuk mengakses Studio Notenux?
                            </span>
                            <i class="fa-solid fa-chevron-down faq-chevron transition-transform duration-200"></i>
                        </button>
                        <div class="faq-content hidden px-5 pb-4 text-xs text-slate-600 dark:text-slate-300 leading-relaxed border-t border-dashed border-black/10 dark:border-white/10 pt-3">
                            Setiap dokumen, level XP gamifikasi, kategori, dan shard kriptografis kamu terikat dengan akun Scribe kamu. Dengan mewajibkan login, catatanmu aman dan tersimpan rapi di database pribadi tanpa risiko benturan dengan data pengguna lain. Jika belum punya akun, kamu dapat mendaftar gratis terlebih dahulu!
                        </div>
                    </div>

                    <!-- FAQ 2 -->
                    <div class="faq-item arcade-card rounded-xl border-2 border-black dark:border-blue-900 bg-[var(--bg-surface)] shadow-[3px_3px_0px_#000] overflow-hidden">
                        <button type="button" class="faq-trigger w-full px-5 py-4 text-left font-bold text-xs sm:text-sm flex items-center justify-between gap-4 cursor-pointer">
                            <span class="flex items-center gap-2 text-blue-950 dark:text-cyan-300">
                                <i class="fa-solid fa-circle-half-stroke text-amber-500"></i> Bagaimana cara kerja Dark dan Light theme?
                            </span>
                            <i class="fa-solid fa-chevron-down faq-chevron transition-transform duration-200"></i>
                        </button>
                        <div class="faq-content hidden px-5 pb-4 text-xs text-slate-600 dark:text-slate-300 leading-relaxed border-t border-dashed border-black/10 dark:border-white/10 pt-3">
                            Kamu dapat mengklik tombol tema di navbar kapan saja untuk berganti antara mode Arcade Day (terang) dan Retro Cyberpunk Night (gelap). Preferensi temamu disimpan secara otomatis di perambanmu (`localStorage`) dan akan langsung diterapkan pada seluruh halaman Studio maupun autentikasi.
                        </div>
                    </div>

                    <!-- FAQ 3 -->
                    <div class="faq-item arcade-card rounded-xl border-2 border-black dark:border-blue-900 bg-[var(--bg-surface)] shadow-[3px_3px_0px_#000] overflow-hidden">
                        <button type="button" class="faq-trigger w-full px-5 py-4 text-left font-bold text-xs sm:text-sm flex items-center justify-between gap-4 cursor-pointer">
                            <span class="flex items-center gap-2 text-blue-950 dark:text-cyan-300">
                                <i class="fa-solid fa-file-arrow-down text-emerald-500"></i> Apakah saya bisa mengekspor catatan saya keluar dari Notenux?
                            </span>
                            <i class="fa-solid fa-chevron-down faq-chevron transition-transform duration-200"></i>
                        </button>
                        <div class="faq-content hidden px-5 pb-4 text-xs text-slate-600 dark:text-slate-300 leading-relaxed border-t border-dashed border-black/10 dark:border-white/10 pt-3">
                            Tentu! Notenux mendukung ekspor 1-klik ke format Markdown standar (`.md`), berkas HTML murni untuk web, dan arsip JSON shard lengkap. Kamu juga bisa mengimpor kembali berkas Markdown ke dalam sistem kapan saja.
                        </div>
                    </div>
                </div>

            </div>
        </section>

        <!-- ============================================================================== -->
        <!-- 6. FINAL CALL TO ACTION -->
        <!-- ============================================================================== -->
        <section class="py-16 sm:py-20 px-4 sm:px-6 bg-[var(--bg-surface-alt)] border-t-2 border-black dark:border-blue-900 text-center font-mono">
            <div class="max-w-3xl mx-auto space-y-6">
                <i class="fa-solid fa-hat-wizard text-4xl text-blue-600 dark:text-cyan-400"></i>
                <h2 class="text-2xl sm:text-4xl font-extrabold tracking-tight">Siap Memulai Petualangan Menulismu?</h2>
                <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                    Masuki Studio Notenux sekarang, nikmati editor Markdown bertenaga, dan raih rank tertinggi sebagai Scribe legendaris.
                </p>
                <div class="pt-4 flex flex-wrap items-center justify-center gap-4">
                    <a href="{{ route('studio') }}"
                       id="bottom-studio-cta"
                       class="arcade-btn px-8 py-4 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-pixel text-xs sm:text-sm tracking-wider shadow-[5px_5px_0px_#000] active:translate-y-1 transition-all">
                        <i class="fa-solid fa-terminal mr-2 text-cyan-300"></i>
                        <span>BUKA STUDIO NOTENUX</span>
                    </a>

                    @guest
                        <a href="{{ route('register') }}"
                           id="bottom-register-cta"
                           class="arcade-btn px-8 py-4 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs sm:text-sm tracking-wider shadow-[5px_5px_0px_#000] active:translate-y-1 transition-all">
                            <i class="fa-solid fa-user-plus mr-2"></i>
                            <span>REGISTER AKUN BARU</span>
                        </a>
                    @endguest
                </div>
            </div>
        </section>

    </main>

    <!-- Footer -->
    <footer class="w-full bg-[var(--bg-surface)] border-t-2 border-black dark:border-blue-900 py-6 px-4 sm:px-6 font-mono text-xs">
        <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-gamepad text-blue-600 dark:text-cyan-400 text-lg"></i>
                <span class="font-pixel text-[11px] text-blue-600 dark:text-cyan-400">NOTENUX STUDIO</span>
                <span class="text-slate-400">&bull;</span>
                <span class="text-slate-500 dark:text-slate-400">&copy; {{ date('Y') }} All Rights Reserved</span>
            </div>

            <div class="flex items-center gap-4 text-[11px] text-slate-600 dark:text-slate-400">
                <a href="{{ route('studio') }}" class="hover:text-blue-600 dark:hover:text-cyan-400 underline">Studio</a>
                @guest
                    <a href="{{ route('login') }}" class="hover:text-blue-600 dark:hover:text-cyan-400 underline">Login</a>
                    <a href="{{ route('register') }}" class="hover:text-blue-600 dark:hover:text-cyan-400 underline">Register</a>
                @else
                    <a href="{{ route('categories.index') }}" class="hover:text-blue-600 dark:hover:text-cyan-400 underline">Kategori</a>
                @endguest
            </div>
        </div>
    </footer>

    <!-- Interactive Landing Page Scripts (Playground, FAQ Accordions, Audio Feedback) -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // 1. FAQ Accordions
            document.querySelectorAll('.faq-trigger').forEach(trigger => {
                trigger.addEventListener('click', () => {
                    const item = trigger.closest('.faq-item');
                    const content = item.querySelector('.faq-content');
                    const chevron = trigger.querySelector('.faq-chevron');
                    const isHidden = content.classList.contains('hidden');

                    document.querySelectorAll('.faq-content').forEach(c => c.classList.add('hidden'));
                    document.querySelectorAll('.faq-chevron').forEach(ch => ch.classList.remove('rotate-180'));

                    if (isHidden) {
                        content.classList.remove('hidden');
                        chevron.classList.add('rotate-180');
                    }
                });
            });

            // 2. Interactive Demo Playground
            const demoEditor = document.getElementById('demo-editor-input');
            const demoWordCount = document.getElementById('demo-word-count');
            const demoCharCount = document.getElementById('demo-char-count');
            const demoXpBar = document.getElementById('demo-xp-bar');
            const demoXpText = document.getElementById('demo-xp-text');
            const demoLevelDisplay = document.getElementById('demo-level-display');
            const demoPreviewContainer = document.getElementById('demo-preview-container');
            const demoEditorContainer = document.getElementById('demo-editor-container');
            const togglePreviewBtn = document.getElementById('toggle-preview-btn');

            let simulatedXp = 25;
            let simulatedLevel = 1;

            if (demoEditor) {
                demoEditor.addEventListener('input', () => {
                    const text = demoEditor.value || '';
                    const words = text.trim() ? text.trim().split(/\s+/).length : 0;
                    const chars = text.length;

                    if (demoWordCount) demoWordCount.textContent = words;
                    if (demoCharCount) demoCharCount.textContent = chars;

                    // XP Level Simulation
                    simulatedXp += 2;
                    if (simulatedXp >= 100) {
                        simulatedLevel += 1;
                        simulatedXp = 10;
                        if (window.notenux?.audio) window.notenux.audio.playSuccess();
                    }

                    if (demoXpBar) demoXpBar.style.width = Math.min(simulatedXp, 100) + '%';
                    if (demoXpText) demoXpText.textContent = `${simulatedXp} / 100 XP`;
                    if (demoLevelDisplay) demoLevelDisplay.textContent = `LV. ${simulatedLevel} ${simulatedLevel > 1 ? 'SCRIBE ADEPT' : 'NOVICE'}`;
                });
            }

            // Font Selector in Demo
            document.querySelectorAll('.demo-font-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const font = btn.getAttribute('data-font');
                    document.querySelectorAll('.demo-font-btn').forEach(b => {
                        b.classList.remove('bg-blue-600', 'text-white');
                        b.classList.add('bg-[var(--bg-surface)]', 'text-[var(--text-main)]');
                    });
                    btn.classList.add('bg-blue-600', 'text-white');
                    btn.classList.remove('bg-[var(--bg-surface)]', 'text-[var(--text-main)]');

                    if (demoEditor) {
                        demoEditor.classList.remove('font-pixel', 'font-arcade', 'font-mono', 'font-sans', 'font-serif');
                        if (font === 'pixel') demoEditor.classList.add('font-pixel');
                        else if (font === 'arcade') demoEditor.classList.add('font-arcade');
                        else if (font === 'mono') demoEditor.classList.add('font-mono');
                        else if (font === 'serif') demoEditor.classList.add('font-serif');
                        else demoEditor.classList.add('font-sans');
                    }
                });
            });

            // Templates
            const templateQuestBtn = document.getElementById('template-quest-btn');
            const templateCodeBtn = document.getElementById('template-code-btn');

            if (templateQuestBtn && demoEditor) {
                templateQuestBtn.addEventListener('click', () => {
                    demoEditor.value = `# ⚔️ Quest Log: The Neon Vault

- [x] Gather cryptographic key shards
- [x] Equip Cyber Spellcaster wand
- [ ] Infiltrate mainframe level 3
- [ ] Save notes into immutable chunks!`;
                    demoEditor.dispatchEvent(new Event('input'));
                });
            }

            if (templateCodeBtn && demoEditor) {
                templateCodeBtn.addEventListener('click', () => {
                    demoEditor.value = `# 💻 Code Shard: Chunk Verification

\`\`\`javascript
const verifyShard = (shard) => {
    const hash = sha256(shard.content);
    return hash === shard.checksum;
};
\`\`\`

Verified: 100% data integrity assured!`;
                    demoEditor.dispatchEvent(new Event('input'));
                });
            }

            // Toggle Preview
            if (togglePreviewBtn && demoPreviewContainer && demoEditorContainer && demoEditor) {
                togglePreviewBtn.addEventListener('click', () => {
                    const isPreview = !demoPreviewContainer.classList.contains('hidden');
                    if (isPreview) {
                        demoPreviewContainer.classList.add('hidden');
                        demoEditorContainer.classList.remove('hidden');
                        togglePreviewBtn.innerHTML = '👁️ Preview';
                    } else {
                        // Render basic markdown into preview
                        const raw = demoEditor.value;
                        const rendered = raw
                            .replace(/^# (.*$)/gim, '<h2 class="text-lg font-bold text-blue-500 mb-1">$1</h2>')
                            .replace(/^- \[x\] (.*$)/gim, '<div class="text-emerald-500 font-mono">✅ $1</div>')
                            .replace(/^- \[ \] (.*$)/gim, '<div class="text-slate-400 font-mono">⬜ $1</div>')
                            .replace(/```([\s\S]*?)```/gm, '<pre class="bg-black/30 p-2 rounded font-mono text-xs my-2">$1</pre>')
                            .replace(/\n/gim, '<br>');
                        demoPreviewContainer.innerHTML = rendered;
                        demoPreviewContainer.classList.remove('hidden');
                        demoEditorContainer.classList.add('hidden');
                        togglePreviewBtn.innerHTML = '✏️ Edit';
                    }
                });
            }
        });
    </script>

</body>
</html>
