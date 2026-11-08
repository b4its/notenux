<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Notenux') }}</title>

    <!-- App Favicon & Touch Icon (Default Light, dynamically adapts to theme) -->
    <link rel="icon" id="app-favicon" type="image/png" href="{{ asset('assets/logo/light/icon_light.png') }}">
    <link rel="apple-touch-icon" id="app-apple-touch-icon" href="{{ asset('assets/logo/light/icon_light.png') }}">

    <!-- Font Awesome 6 Free CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <!-- Google Fonts for Retro Aesthetics -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;600&family=Instrument+Sans:wght@400;500;600;700&family=Merriweather:ital,wght@0,300;0,400;0,700;1,300&family=Press+Start+2P&family=VT323&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Inline Head Script for Instant Theme & Favicon Synchronization -->
    <script>
        (function() {
            const savedTheme = localStorage.getItem('notenux_theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            const lightFavicon = "{{ asset('assets/logo/light/icon_light.png') }}";
            const darkFavicon = "{{ asset('assets/logo/dark/icon_dark.png') }}";
            window.notenuxFaviconLight = lightFavicon;
            window.notenuxFaviconDark = darkFavicon;

            function applyFavicon(theme) {
                const target = theme === 'dark' ? darkFavicon : lightFavicon;
                const fav = document.getElementById('app-favicon') || document.querySelector('link[rel="icon"]');
                const apple = document.getElementById('app-apple-touch-icon') || document.querySelector('link[rel="apple-touch-icon"]');
                if (fav) fav.setAttribute('href', target);
                if (apple) apple.setAttribute('href', target);
            }

            if (savedTheme === 'dark') {
                document.documentElement.classList.add('dark');
                document.documentElement.setAttribute('data-theme', 'dark');
                applyFavicon('dark');
            } else {
                document.documentElement.classList.remove('dark');
                document.documentElement.setAttribute('data-theme', 'light');
                applyFavicon('light');
            }

            if (!localStorage.getItem('notenux_theme') && window.matchMedia) {
                window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
                    const nextTheme = e.matches ? 'dark' : 'light';
                    if (nextTheme === 'dark') {
                        document.documentElement.classList.add('dark');
                        document.documentElement.setAttribute('data-theme', 'dark');
                    } else {
                        document.documentElement.classList.remove('dark');
                        document.documentElement.setAttribute('data-theme', 'light');
                    }
                    applyFavicon(nextTheme);
                });
            }
        })();
    </script>
</head>
<body class="retro-crt-screen bg-[var(--bg-main)] text-[var(--text-main)] min-h-screen font-sans flex flex-col antialiased selection:bg-blue-600 selection:text-white transition-colors duration-200">

    <!-- Top Navigation Header -->
    <header class="sticky top-0 w-full bg-[var(--bg-surface)] border-b-2 border-black dark:border-blue-900 shadow-[0_4px_0_rgba(0,0,0,0.25)] px-2 sm:px-4 lg:px-6 py-2.5 sm:py-3 z-40">
        <div class="max-w-7xl mx-auto flex items-center justify-between gap-2 sm:gap-4">

            <!-- Brand Logo (Transparent Logo) -->
            <a href="{{ route('landing') }}" class="flex items-center gap-1.5 sm:gap-2.5 group transition-transform active:translate-y-0.5 shrink-0 min-w-0" title="Notenux Studio">
                <!-- Adaptive Horizontal Logo without background container -->
                <img src="{{ asset('assets/logo/dark/icon_horizontal_dark.png') }}"
                     alt="Notenux Studio"
                     class="h-7 sm:h-9 w-auto object-contain hidden dark:block transition-transform duration-200 group-hover:scale-105">
                <img src="{{ asset('assets/logo/light/icon_horizontal_light.png') }}"
                     alt="Notenux Studio"
                     class="h-7 sm:h-9 w-auto object-contain block dark:hidden transition-transform duration-200 group-hover:scale-105">
                <span class="sr-only">NOTENUX</span>
                <span class="text-[9px] font-mono bg-blue-900/20 text-blue-600 dark:text-cyan-300 px-1 sm:px-1.5 py-0.5 rounded border border-blue-500/40 font-bold uppercase hidden xs:inline-block">v3.0</span>
            </a>

            <!-- Navigation Links (Desktop) -->
            <nav class="hidden md:flex items-center gap-5 lg:gap-6 font-mono text-xs font-bold shrink-0">
                <a href="#fitur" class="hover:text-blue-600 dark:hover:text-cyan-400 transition-colors">MANFAAT & FITUR</a>
                <a href="#playground" class="hover:text-blue-600 dark:hover:text-cyan-400 transition-colors">DEMO INTERAKTIF</a>
                <a href="#keamanan" class="hover:text-blue-600 dark:hover:text-cyan-400 transition-colors">UJI PROTEKSI</a>
                <a href="#rank-codex" class="hover:text-blue-600 dark:hover:text-cyan-400 transition-colors">RANK SCRIBE</a>
                <a href="#faq" class="hover:text-blue-600 dark:hover:text-cyan-400 transition-colors">FAQ</a>
            </nav>

            <!-- Right Controls & Authentication Actions -->
            <div class="flex items-center gap-1.5 sm:gap-3 shrink-0">
                <!-- Sound Toggle -->
                <button id="sound-toggle-btn"
                        class="arcade-btn bg-blue-100 hover:bg-blue-200 dark:bg-blue-950 dark:hover:bg-blue-900 text-blue-900 dark:text-blue-300 px-2 sm:px-2.5 py-1.5 rounded text-xs font-mono shrink-0"
                        title="Toggle 8-bit Audio SFX">
                    <i class="fa-solid fa-volume-high text-blue-600 dark:text-cyan-400 sm:mr-1"></i> <span class="hidden xl:inline">SFX</span>
                </button>

                <!-- CRT Scanline Toggle -->
                <button id="crt-toggle-btn"
                        class="arcade-btn bg-purple-100 hover:bg-purple-200 dark:bg-purple-900/40 dark:hover:bg-purple-800/60 text-purple-900 dark:text-purple-300 px-2 sm:px-2.5 py-1.5 rounded text-xs font-mono hidden sm:inline-flex shrink-0"
                        title="Toggle Retro CRT Scanlines">
                    <i class="fa-solid fa-tv sm:mr-1 text-purple-600 dark:text-purple-400"></i> <span class="hidden xl:inline">CRT</span>
                </button>

                <!-- Theme Toggle Button -->
                <button id="theme-toggle-btn"
                        class="arcade-btn bg-amber-300 hover:bg-amber-400 dark:bg-blue-900 dark:hover:bg-blue-800 text-black dark:text-cyan-300 px-2 sm:px-2.5 py-1.5 rounded text-xs font-mono shrink-0"
                        title="Toggle Light / Dark Mode">
                    <i class="fa-solid fa-moon text-cyan-300 sm:mr-1.5"></i> <span class="hidden sm:inline">TEMA</span>
                </button>

                <!-- Auth State -->
                @auth
                    <!-- User is logged in -->
                    <div class="flex items-center gap-1.5 sm:gap-2">
                        <div class="hidden sm:flex items-center gap-1.5 px-2.5 py-1 rounded-lg border border-black dark:border-blue-700 bg-[var(--bg-surface-alt)] text-xs font-mono">
                            <i class="fa-solid fa-user-ninja text-emerald-500"></i>
                            <span class="font-bold truncate max-w-[120px]">{{ auth()->user()->name }}</span>
                        </div>

                        <a href="{{ route('studio') }}"
                           id="nav-studio-btn"
                           class="arcade-btn bg-blue-600 hover:bg-blue-500 text-white px-2.5 sm:px-4 py-1.5 rounded text-xs font-mono font-bold tracking-wider shrink-0">
                            <i class="fa-solid fa-terminal sm:mr-1.5"></i> <span class="hidden xs:inline">STUDIO</span>
                        </a>

                        <form method="POST" action="{{ route('logout') }}" class="inline">
                            @csrf
                            <button type="submit"
                                    id="nav-logout-btn"
                                    class="arcade-btn bg-red-600 hover:bg-red-500 text-white px-2 sm:px-2.5 py-1.5 rounded text-xs font-mono shrink-0"
                                    title="Logout dari akun">
                                <i class="fa-solid fa-right-from-bracket"></i>
                            </button>
                        </form>
                    </div>
                @else
                    <!-- User is guest -->
                    <a href="{{ route('login') }}"
                       id="nav-login-btn"
                       class="arcade-btn bg-slate-200 hover:bg-slate-300 dark:bg-slate-800 dark:hover:bg-slate-700 text-xs font-mono px-2.5 sm:px-3 py-1.5 rounded shrink-0">
                        <i class="fa-solid fa-right-to-bracket sm:mr-1"></i> <span>MASUK</span>
                    </a>

                    <a href="{{ route('register') }}"
                       id="nav-register-btn"
                       class="arcade-btn bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-mono px-3 py-1.5 rounded hidden sm:inline-flex shrink-0">
                        <i class="fa-solid fa-user-plus mr-1"></i> <span>REGISTER</span>
                    </a>

                    <a href="{{ route('studio') }}"
                       id="nav-studio-guest-btn"
                       class="arcade-btn bg-blue-600 hover:bg-blue-500 text-white px-2.5 sm:px-4 py-1.5 rounded text-xs font-mono font-bold tracking-wider shrink-0">
                        <i class="fa-solid fa-terminal sm:mr-1.5"></i> <span class="hidden xs:inline">STUDIO</span>
                    </a>
                @endauth
            </div>

        </div>
    </header>

    <!-- Main Content Area -->
    <main class="flex-1">

        <!-- ============================================================================== -->
        <!-- 1. HERO SECTION & INTERACTIVE LIVE STATION -->
        <!-- ============================================================================== -->
        <section class="relative overflow-hidden py-10 sm:py-16 lg:py-20 px-4 sm:px-6">
            <!-- Retro Cyberpunk Background Accents -->
            <div class="absolute -top-24 left-1/2 -translate-x-1/2 w-[700px] h-[350px] bg-blue-500/10 dark:bg-cyan-500/10 blur-3xl pointer-events-none rounded-full"></div>
            <div class="absolute top-1/2 -left-20 w-72 h-72 bg-purple-500/10 dark:bg-purple-600/10 blur-3xl pointer-events-none rounded-full"></div>

            <div class="max-w-5xl mx-auto text-center space-y-6 relative z-10">

                <!-- Hero Portrait Logo Emblem (Transparent Background without container) -->
                <div class="flex justify-center mb-2 sm:mb-4">
                    <div class="relative group transition-transform duration-300 hover:scale-105">
                        <img src="{{ asset('assets/logo/dark/icon_vertical_dark.png') }}"
                             alt="Notenux Studio Portrait Emblem"
                             class="w-24 xs:w-28 sm:w-36 md:w-40 lg:w-44 h-auto object-contain hidden dark:block filter drop-shadow-[0_10px_25px_rgba(37,99,235,0.25)]">
                        <img src="{{ asset('assets/logo/light/icon_vertical_light.png') }}"
                             alt="Notenux Studio Portrait Emblem"
                             class="w-24 xs:w-28 sm:w-36 md:w-40 lg:w-44 h-auto object-contain block dark:hidden filter drop-shadow-[0_10px_25px_rgba(26,85,224,0.2)]">
                    </div>
                </div>

                <!-- Badge -->
                <div class="inline-flex items-center gap-1.5 sm:gap-2 px-3 py-1 rounded-full border-2 border-black dark:border-cyan-400/50 bg-blue-100 dark:bg-blue-950/70 text-blue-900 dark:text-cyan-300 font-pixel text-[9px] sm:text-[10px] tracking-wider shadow-[3px_3px_0px_#000] max-w-full">
                    <i class="fa-solid fa-bolt text-amber-500 animate-pulse"></i>
                    <span>RETRO GAMIFIED NOTE STUDIO • LEVEL UP YOUR WRITING</span>
                </div>

                <!-- Main Headline -->
                <h1 class="text-2xl xs:text-3xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight text-slate-900 dark:text-white leading-tight break-words">
                    Abadikan Pemikiran. <br class="hidden sm:block">
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-600 via-cyan-500 to-indigo-600 dark:from-cyan-300 dark:via-blue-400 dark:to-purple-400">
                        Tingkatkan Level Scribe.
                    </span>
                </h1>

                <!-- Subheadline (Pure User Benefits & Capabilities) -->
                <p class="max-w-2xl mx-auto text-xs sm:text-sm md:text-base text-slate-600 dark:text-slate-300 font-mono leading-relaxed px-2">
                    Aplikasi catatan retro yang membuatmu betah menulis setiap hari. Nikmati editor Markdown serba cepat, jaminan naskah aman tanpa risiko hilang, dan sistem leveling RPG yang mengubah produktivitasmu menjadi petualangan seru.
                </p>

                <!-- Hero Action Buttons -->
                <div class="pt-2 flex flex-col xs:flex-row flex-wrap items-center justify-center gap-2.5 sm:gap-4 font-mono w-full px-2">
                    <!-- Primary CTA leading to /studio -->
                    <a href="{{ route('studio') }}"
                       id="hero-studio-cta"
                       class="arcade-btn w-full xs:w-auto text-center px-5 py-3 sm:px-6 sm:py-3.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-pixel text-xs sm:text-sm tracking-wider shadow-[5px_5px_0px_#000] active:translate-y-1 transition-all">
                        <i class="fa-solid fa-terminal mr-2 text-cyan-300"></i>
                        <span>BUKA STUDIO</span>
                    </a>

                    @guest
                        <a href="{{ route('register') }}"
                           id="hero-register-cta"
                           class="arcade-btn w-full xs:w-auto text-center px-5 py-3 sm:px-6 sm:py-3.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs sm:text-sm tracking-wider shadow-[5px_5px_0px_#000] active:translate-y-1 transition-all">
                            <i class="fa-solid fa-user-plus mr-2"></i>
                            <span>DAFTAR SCRIBE GRATIS</span>
                        </a>

                        <a href="{{ route('login') }}"
                           id="hero-login-cta"
                           class="arcade-btn w-full xs:w-auto text-center px-4 py-3 sm:px-5 sm:py-3.5 rounded-xl bg-[var(--bg-surface)] hover:bg-slate-200 dark:hover:bg-blue-900/40 text-[var(--text-main)] font-bold text-xs sm:text-sm tracking-wider shadow-[4px_4px_0px_#000] active:translate-y-1 transition-all">
                            <i class="fa-solid fa-right-to-bracket mr-1.5 text-blue-500"></i>
                            <span>MASUK</span>
                        </a>
                    @else
                        <a href="{{ route('categories.index') }}"
                           class="arcade-btn w-full xs:w-auto text-center px-5 py-3.5 rounded-xl bg-[var(--bg-surface)] hover:bg-slate-200 dark:hover:bg-blue-900/40 text-[var(--text-main)] font-bold text-xs sm:text-sm tracking-wider shadow-[4px_4px_0px_#000] active:translate-y-1 transition-all">
                            <i class="fa-solid fa-folder-open mr-1.5 text-cyan-400"></i>
                            <span>KELOLA KATEGORI</span>
                        </a>
                    @endguest
                </div>

                <!-- ============================================================================== -->
                <!-- HERO INTERACTIVE SCRIBE STATION (Mini Live Console Deck) -->
                <!-- ============================================================================== -->
                <div class="pt-6 max-w-3xl mx-auto text-left font-mono">
                    <div class="arcade-card rounded-2xl border-2 border-black dark:border-blue-700 bg-[var(--bg-surface)] shadow-[6px_6px_0px_#000] overflow-hidden">

                        <!-- Station Header & Tabs -->
                        <div class="px-3 sm:px-4 py-2.5 bg-[var(--bg-surface-alt)] border-b-2 border-black dark:border-blue-900 flex flex-col xs:flex-row items-start xs:items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <span class="w-3 h-3 rounded-full bg-rose-500 border border-black/40 inline-block"></span>
                                <span class="w-3 h-3 rounded-full bg-amber-500 border border-black/40 inline-block"></span>
                                <span class="w-3 h-3 rounded-full bg-emerald-500 border border-black/40 inline-block"></span>
                                <span class="text-[10px] font-bold text-slate-600 dark:text-cyan-400 font-pixel uppercase ml-1">STATION DECK: LIVE TERMINAL</span>
                            </div>

                            <!-- Tabs -->
                            <div class="flex items-center gap-1 flex-wrap w-full xs:w-auto">
                                <button type="button" id="tab-btn-quests" class="station-tab-btn px-2 sm:px-2.5 py-1 rounded text-[10px] font-bold bg-blue-600 text-white arcade-btn" data-tab="quests">
                                    <i class="fa-solid fa-khanda mr-1"></i> Misi Menulis
                                </button>
                                <button type="button" id="tab-btn-hasher" class="station-tab-btn px-2 sm:px-2.5 py-1 rounded text-[10px] font-bold bg-[var(--bg-surface)] text-[var(--text-main)] arcade-btn" data-tab="hasher">
                                    <i class="fa-solid fa-shield-halved mr-1"></i> Proteksi Teks
                                </button>
                                <button type="button" id="tab-btn-fonts" class="station-tab-btn px-2 sm:px-2.5 py-1 rounded text-[10px] font-bold bg-[var(--bg-surface)] text-[var(--text-main)] arcade-btn" data-tab="fonts">
                                    <i class="fa-solid fa-font mr-1"></i> Atmosfer Font
                                </button>
                            </div>
                        </div>

                        <!-- Tab 1: Scribe Quests (Gamified Onboarding Station) -->
                        <div id="station-panel-quests" class="station-panel p-4 space-y-3">
                            <div class="flex items-center justify-between gap-2 text-xs">
                                <div class="flex items-center gap-2">
                                    <span class="font-pixel text-[11px] text-amber-500" id="hero-quest-rank">LV. 1 NOVICE SCRIBE</span>
                                    <span class="text-[10px] text-slate-500 dark:text-slate-400">&bull;</span>
                                    <span class="text-[11px] font-bold text-cyan-500" id="hero-quest-xp-text">25 / 100 XP</span>
                                </div>
                                <button type="button" id="hero-reset-quests-btn" class="text-[10px] text-slate-500 dark:text-slate-400 hover:text-rose-500 underline">
                                    <i class="fa-solid fa-rotate-left mr-0.5"></i> Reset Misi
                                </button>
                            </div>

                            <!-- XP Progress Bar -->
                            <div class="w-full bg-black/20 dark:bg-black/60 h-2.5 rounded border border-black/30 overflow-hidden relative">
                                <div id="hero-quest-xp-bar" class="h-full bg-gradient-to-r from-amber-500 via-emerald-400 to-cyan-400 transition-all duration-300" style="width: 25%;"></div>
                            </div>

                            <!-- Quests Checklist (Interactive Click to Complete) -->
                            <div class="space-y-2 pt-1 text-xs">
                                <div id="quest-item-1" class="quest-item flex items-center justify-between p-2 rounded-lg border border-black/15 dark:border-blue-900 bg-[var(--bg-surface-alt)] cursor-pointer hover:border-blue-500 transition-colors gap-2" data-xp="25" data-completed="true">
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <i class="fa-solid fa-circle-check text-emerald-500 text-sm quest-check-icon shrink-0"></i>
                                        <span class="font-bold line-through text-slate-500 dark:text-slate-400 quest-title truncate">Buka Notenux & Mulai Petualangan</span>
                                    </div>
                                    <span class="text-[10px] font-pixel text-emerald-500 font-bold quest-badge shrink-0">+25 XP [DONE]</span>
                                </div>

                                <div id="quest-item-2" class="quest-item flex items-center justify-between p-2 rounded-lg border border-black/15 dark:border-blue-900 bg-[var(--bg-surface-alt)] cursor-pointer hover:border-blue-500 transition-colors gap-2" data-xp="25" data-completed="false">
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <i class="fa-regular fa-circle text-slate-400 text-sm quest-check-icon shrink-0"></i>
                                        <span class="font-bold quest-title truncate">Tulis Catatan Pertamamu dengan Markdown</span>
                                    </div>
                                    <span class="text-[10px] font-pixel text-amber-500 font-bold quest-badge shrink-0">+25 XP</span>
                                </div>

                                <div id="quest-item-3" class="quest-item flex items-center justify-between p-2 rounded-lg border border-black/15 dark:border-blue-900 bg-[var(--bg-surface-alt)] cursor-pointer hover:border-blue-500 transition-colors gap-2" data-xp="50" data-completed="false">
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <i class="fa-regular fa-circle text-slate-400 text-sm quest-check-icon shrink-0"></i>
                                        <span class="font-bold quest-title truncate">Uji Proteksi Keamanan Tulisanmu</span>
                                    </div>
                                    <span class="text-[10px] font-pixel text-cyan-400 font-bold quest-badge shrink-0">+50 XP</span>
                                </div>
                            </div>

                            <!-- Celebration Level Up Message (Hidden by default) -->
                            <div id="hero-levelup-banner" class="hidden p-3 rounded-xl border-2 border-amber-400 bg-amber-400/10 text-center space-y-1 animate-bounce">
                                <div class="font-pixel text-xs text-amber-500">🎉 MISI SELESAI! LEVEL UP!</div>
                                <div class="text-[11px] text-slate-600 dark:text-slate-300">Selamat! Kamu dipromosikan menjadi <strong class="text-cyan-400">Apprentice Scribe</strong>. Buka Studio untuk melanjutkan petualanganmu!</div>
                            </div>
                        </div>

                        <!-- Tab 2: Live Text Safety Check -->
                        <div id="station-panel-hasher" class="station-panel hidden p-4 space-y-3">
                            <p class="text-xs text-slate-600 dark:text-slate-300">
                                Ketik teks untuk melihat bagaimana sistem mengunci dan melindungi keaslian tulisanmu secara otomatis tanpa risiko tertimpa:
                            </p>
                            <div class="space-y-2">
                                <input type="text"
                                       id="hasher-input"
                                       class="w-full bg-[var(--bg-main)] px-3 py-2 rounded-lg border border-black/30 dark:border-blue-800 text-xs font-mono outline-none focus:border-cyan-400"
                                       placeholder="Ketik teks naskah catatan di sini..."
                                       value="Catatan Penting Notenux: Ide Cemerlang Hari Ini">
                                <div class="p-2.5 rounded-lg border border-black/20 dark:border-blue-900 bg-black/40 font-mono text-[11px] space-y-1">
                                    <div class="flex items-center justify-between text-slate-400 text-[10px]">
                                        <span>STATUS KEAMANAN NASKAH</span>
                                        <span class="text-emerald-400 font-bold"><i class="fa-solid fa-shield-halved"></i> 100% AMAN & TERKUNCI</span>
                                    </div>
                                    <div id="hasher-hash-output" class="text-cyan-400 font-bold break-all select-all">
                                        5e884898da28047151d0e56f8dc6292773603d0d6aabbdd62a11ef721d1542d8
                                    </div>
                                </div>
                                <div class="flex flex-col xs:flex-row items-start xs:items-center justify-between gap-2 text-[10px] text-slate-500 dark:text-slate-400">
                                    <span>Panjang Karakter: <strong id="hasher-char-count">51</strong> &bull; Ukuran: <strong id="hasher-byte-count">51 B</strong></span>
                                    <button type="button" id="copy-hash-btn" class="arcade-btn bg-blue-600 text-white px-2.5 py-1 rounded text-[10px]">
                                        <i class="fa-solid fa-copy mr-1"></i> Salin Kunci
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Tab 3: Font Atmosphere Lab -->
                        <div id="station-panel-fonts" class="station-panel hidden p-4 space-y-3">
                            <div class="flex flex-col xs:flex-row items-start xs:items-center justify-between gap-2 text-xs">
                                <span class="text-slate-600 dark:text-slate-400">Pilih font yang paling pas dengan gaya menulismu:</span>
                                <div class="flex items-center gap-1 flex-wrap">
                                    <button type="button" class="hero-font-btn px-2 py-0.5 rounded text-[10px] bg-blue-600 text-white font-pixel" data-font="pixel">Pixel</button>
                                    <button type="button" class="hero-font-btn px-2 py-0.5 rounded text-[10px] bg-[var(--bg-surface)] text-[var(--text-main)] font-arcade" data-font="arcade">Arcade</button>
                                    <button type="button" class="hero-font-btn px-2 py-0.5 rounded text-[10px] bg-[var(--bg-surface)] text-[var(--text-main)] font-mono" data-font="mono">Fira</button>
                                    <button type="button" class="hero-font-btn px-2 py-0.5 rounded text-[10px] bg-[var(--bg-surface)] text-[var(--text-main)] font-sans" data-font="sans">Sans</button>
                                    <button type="button" class="hero-font-btn px-2 py-0.5 rounded text-[10px] bg-[var(--bg-surface)] text-[var(--text-main)] font-serif" data-font="serif">Serif</button>
                                </div>
                            </div>

                            <div id="hero-font-preview-box" class="p-3.5 rounded-lg border-2 border-dashed border-blue-500/30 bg-[var(--bg-main)] font-pixel text-xs sm:text-sm leading-relaxed text-slate-800 dark:text-slate-100 min-h-[80px] flex items-center">
                                "Pena adalah pedang sang pemikir; dan setiap goresan tulisan di Notenux tersimpan aman untuk selamanya."
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Hero Metrics Telemetry Strip (Responsive 2-column) -->
                <div class="pt-6 max-w-xl mx-auto grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4 font-mono text-left w-full px-2">
                    <div class="arcade-card p-3.5 rounded-xl border-2 border-black dark:border-blue-900 bg-[var(--bg-surface)] shadow-[3px_3px_0px_#000] flex items-center justify-between sm:block">
                        <div>
                            <div class="flex items-center gap-2 text-blue-600 dark:text-cyan-400 text-xs font-bold">
                                <i class="fa-solid fa-scroll"></i>
                                <span>MANUSCRIPTS</span>
                            </div>
                            <div class="mt-1 font-pixel text-lg sm:text-xl text-slate-900 dark:text-white" id="telemetry-notes">{{ $noteCount }}+</div>
                            <p class="text-[10px] text-slate-500 dark:text-slate-400">Catatan tersimpan aman</p>
                        </div>
                        <div class="sm:hidden text-2xl text-blue-500/40">
                            <i class="fa-solid fa-scroll"></i>
                        </div>
                    </div>

                    <div class="arcade-card p-3.5 rounded-xl border-2 border-black dark:border-blue-900 bg-[var(--bg-surface)] shadow-[3px_3px_0px_#000] flex items-center justify-between sm:block">
                        <div>
                            <div class="flex items-center gap-2 text-amber-600 dark:text-yellow-400 text-xs font-bold">
                                <i class="fa-solid fa-award"></i>
                                <span>RPG LEVELING</span>
                            </div>
                            <div class="mt-1 font-pixel text-lg sm:text-xl text-slate-900 dark:text-white">LV.50+</div>
                            <p class="text-[10px] text-slate-500 dark:text-slate-400">XP & Gelar Prestasi Scribe</p>
                        </div>
                        <div class="sm:hidden text-2xl text-amber-500/40">
                            <i class="fa-solid fa-award"></i>
                        </div>
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
                        Gunakan Spell Bar Markdown, pilih font retro, rasakan kenaikan XP secara real-time saat mengetik, salin naskah, dan tonton live preview!
                    </p>
                </div>

                <!-- Interactive Playground Console -->
                <div class="arcade-card rounded-2xl border-2 border-black dark:border-blue-700 bg-[var(--bg-surface)] shadow-[8px_8px_0px_#000] overflow-hidden">

                    <!-- Console Top Bar: Font Selector & Templates -->
                    <div class="px-3 sm:px-4 py-3 border-b-2 border-black dark:border-blue-900 bg-[var(--bg-surface-alt)] flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2.5">
                        <!-- Font Selector Switches -->
                        <div class="flex items-center gap-1 sm:gap-1.5 flex-wrap">
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

                        <!-- Template Quick-Insert Buttons & Preview Toggle -->
                        <div class="flex items-center gap-1 sm:gap-1.5 text-xs font-mono flex-wrap">
                            <span class="text-[10px] font-bold text-slate-600 dark:text-slate-400 mr-1 hidden sm:inline">TEMPLATE:</span>
                            <button type="button" id="template-quest-btn" class="arcade-btn bg-amber-400 hover:bg-amber-300 text-black text-[10px] font-mono px-2 py-1 rounded" title="Muat template Quest">
                                ⚔️ Quest
                            </button>
                            <button type="button" id="template-code-btn" class="arcade-btn bg-cyan-600 hover:bg-cyan-500 text-white text-[10px] font-mono px-2 py-1 rounded" title="Muat template Code">
                                💻 Code Notes
                            </button>
                            <button type="button" id="template-table-btn" class="arcade-btn bg-emerald-600 hover:bg-emerald-500 text-white text-[10px] font-mono px-2 py-1 rounded" title="Muat template Tabel">
                                📊 Markdown Table
                            </button>
                            <button type="button" id="toggle-preview-btn" class="arcade-btn bg-purple-600 hover:bg-purple-500 text-white text-[10px] font-mono px-2.5 py-1 rounded" title="Toggle Preview">
                                👁️ Preview
                            </button>
                        </div>
                    </div>

                    <!-- Spell Bar (Markdown Formatting Toolbar) -->
                    <div class="px-3 sm:px-4 py-2 border-b border-black/20 dark:border-blue-900/60 bg-[var(--bg-surface)] flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 text-xs font-mono">
                        <div class="flex items-center gap-1 flex-wrap">
                            <span class="text-[10px] text-slate-500 dark:text-slate-400 mr-1 font-bold">SPELL BAR:</span>
                            <button type="button" class="spell-btn arcade-btn px-2 py-0.5 rounded text-[11px] bg-[var(--bg-surface-alt)] font-bold" data-action="bold" title="Tebal (Ctrl+B)">
                                <strong>B</strong>
                            </button>
                            <button type="button" class="spell-btn arcade-btn px-2 py-0.5 rounded text-[11px] bg-[var(--bg-surface-alt)] italic" data-action="italic" title="Miring (Ctrl+I)">
                                <em>I</em>
                            </button>
                            <button type="button" class="spell-btn arcade-btn px-2 py-0.5 rounded text-[11px] bg-[var(--bg-surface-alt)] font-bold" data-action="heading" title="Heading">
                                H2
                            </button>
                            <button type="button" class="spell-btn arcade-btn px-2 py-0.5 rounded text-[11px] bg-[var(--bg-surface-alt)]" data-action="task" title="Checklist Task">
                                ☑️ Task
                            </button>
                            <button type="button" class="spell-btn arcade-btn px-2 py-0.5 rounded text-[11px] bg-[var(--bg-surface-alt)]" data-action="code" title="Code Block">
                                <code>&lt;/&gt;</code>
                            </button>
                            <button type="button" class="spell-btn arcade-btn px-2 py-0.5 rounded text-[11px] bg-[var(--bg-surface-alt)]" data-action="quote" title="Quote">
                                💬 Quote
                            </button>
                        </div>

                        <!-- Clipboard and Quick Download Actions -->
                        <div class="flex items-center gap-1.5 w-full sm:w-auto justify-end">
                            <button type="button" id="demo-copy-btn" class="arcade-btn px-2 py-1 rounded text-[10px] bg-slate-200 hover:bg-slate-300 dark:bg-slate-800 dark:hover:bg-slate-700 text-[var(--text-main)]" title="Salin seluruh naskah Markdown">
                                <i class="fa-solid fa-copy mr-1 text-cyan-500"></i> Salin
                            </button>
                            <button type="button" id="demo-download-btn" class="arcade-btn px-2 py-1 rounded text-[10px] bg-emerald-600 hover:bg-emerald-500 text-white" title="Unduh berkas .md">
                                <i class="fa-solid fa-download mr-1"></i> Unduh .md
                            </button>
                        </div>
                    </div>

                    <!-- Telemetry Stats Simulation Bar -->
                    <div class="px-3 sm:px-4 py-2.5 border-b-2 border-dashed border-black/10 dark:border-white/10 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2.5 text-xs font-mono">
                        <div class="flex items-center gap-2 sm:gap-3 flex-wrap">
                            <span class="font-pixel text-[10px] text-blue-600 dark:text-cyan-400" id="demo-level-display">LV. 1 NOVICE</span>
                            <div class="w-24 xs:w-32 bg-black/20 dark:bg-black/60 h-2 rounded border border-black/30 overflow-hidden">
                                <div id="demo-xp-bar" class="h-full bg-gradient-to-r from-blue-500 to-cyan-400 transition-all duration-200" style="width: 25%;"></div>
                            </div>
                            <span class="text-[10px] font-bold text-amber-500" id="demo-xp-text">25 / 100 XP</span>
                        </div>

                        <div class="flex items-center gap-1.5 sm:gap-3 text-[10px] sm:text-[11px] text-slate-600 dark:text-slate-400 flex-wrap">
                            <span><strong id="demo-word-count">24</strong> kata</span>
                            <span>&bull;</span>
                            <span><strong id="demo-char-count">142</strong> karakter</span>
                            <span>&bull;</span>
                            <span class="hidden xs:inline"><strong id="demo-line-count">7</strong> baris</span>
                            <span class="hidden xs:inline">&bull;</span>
                            <span class="text-cyan-500 font-bold" id="demo-read-time">~1 mnt baca</span>
                            <span>&bull;</span>
                            <span class="text-emerald-500 font-bold"><i class="fa-solid fa-circle-check"></i> Auto-Synced</span>
                        </div>
                    </div>

                    <!-- Editor Workspace (Split / Interactive Area) -->
                    <div class="p-4 sm:p-6 min-h-[220px]">
                        <!-- Interactive Editor Box -->
                        <div id="demo-editor-container">
                            <textarea id="demo-editor-input"
                                      rows="8"
                                      class="w-full bg-transparent border-0 outline-none resize-none font-pixel text-xs sm:text-sm leading-relaxed text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:ring-0"
                                      placeholder="Ketik sesuatu di sini untuk merasakan simulasi level XP dan font retro..."># 📜 Petualangan Menulis: Naskah Rahasia

Abadikan ide terbaikmu bersama Notenux:
- [x] Tulis draf pertama tanpa gangguan
- [x] Lindungi naskah secara otomatis
- [ ] Raih Level 50 dan gelar Scribe tertinggi!

Coba ketuk tombol Spell Bar di atas atau ubah font sesukamu!</textarea>
                        </div>

                        <!-- Rendered Preview Box (Hidden by default, toggled with Preview button) -->
                        <div id="demo-preview-container" class="hidden p-4 rounded-lg border-2 border-dashed border-blue-500/40 bg-[var(--bg-main)] font-sans text-sm space-y-2"></div>
                    </div>

                    <!-- Playground Footer CTA -->
                    <div class="px-3 sm:px-4 py-3 bg-[var(--bg-surface-alt)] border-t-2 border-black dark:border-blue-900 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 text-xs font-mono">
                        <span class="text-slate-600 dark:text-slate-400">
                            <i class="fa-solid fa-lightbulb text-amber-400 mr-1"></i> Ini simulasi ringkas. Di Studio lengkap kamu mendapatkan manajemen kategori, preview modal, ekspor multi-format, dan mode Notepad!
                        </span>
                        <a href="{{ route('studio') }}" class="arcade-btn w-full sm:w-auto text-center bg-blue-600 hover:bg-blue-500 text-white text-xs font-mono px-3.5 py-1.5 rounded font-bold shrink-0">
                            BUKA STUDIO LENGKAP &rarr;
                        </a>
                    </div>

                </div>

            </div>
        </section>

        <!-- ============================================================================== -->
        <!-- 3. MANFAAT & APA YANG BISA KAMU LAKUKAN (FEATURE ARSENAL) -->
        <!-- ============================================================================== -->
        <section id="fitur" class="py-16 sm:py-20 px-4 sm:px-6">
            <div class="max-w-6xl mx-auto space-y-10">

                <div class="text-center space-y-2">
                    <div class="inline-flex items-center gap-1.5 px-3 py-0.5 rounded border border-blue-500/40 bg-blue-500/10 text-blue-600 dark:text-cyan-400 font-pixel text-[10px]">
                        <i class="fa-solid fa-shield-halved"></i> MANFAAT & KEMAMPUAN
                    </div>
                    <h2 class="text-2xl sm:text-4xl font-extrabold tracking-tight">Segala yang Kamu Butuhkan untuk Menulis Lebih Hebat</h2>
                    <p class="text-xs sm:text-sm font-mono text-slate-600 dark:text-slate-400 max-w-2xl mx-auto">
                        Fokus pada apa yang kamu dapatkan: tulisan selalu aman, alur berpikir tanpa hambatan, dan dorongan semangat menulis setiap hari.
                    </p>
                </div>

                <!-- 6 Feature Benefit Cards Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 font-mono">

                    <!-- Card 1: Data Protection Benefit -->
                    <div class="arcade-card p-6 rounded-xl border-2 border-black dark:border-blue-900 bg-[var(--bg-surface)] shadow-[5px_5px_0px_#000] space-y-3 hover:-translate-y-1 transition-transform">
                        <div class="w-12 h-12 rounded-lg bg-blue-600 text-white border-2 border-black shadow-[2px_2px_0px_#000] flex items-center justify-center text-xl">
                            <i class="fa-solid fa-shield-halved"></i>
                        </div>
                        <h3 class="font-pixel text-xs sm:text-sm text-blue-950 dark:text-cyan-300">PERLINDUNGAN DATA OTOMATIS</h3>
                        <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                            <strong>Manfaat untukmu:</strong> Catatanmu selalu aman tanpa khawatir tulisan rusak, tertimpa, atau hilang. Kamu bisa menulis dengan tenang karena setiap kalimat terjaga keutuhannya.
                        </p>
                    </div>

                    <!-- Card 2: RPG Gamification Benefit -->
                    <div class="arcade-card p-6 rounded-xl border-2 border-black dark:border-blue-900 bg-[var(--bg-surface)] shadow-[5px_5px_0px_#000] space-y-3 hover:-translate-y-1 transition-transform">
                        <div class="w-12 h-12 rounded-lg bg-amber-500 text-black border-2 border-black shadow-[2px_2px_0px_#000] flex items-center justify-center text-xl">
                            <i class="fa-solid fa-hat-wizard"></i>
                        </div>
                        <h3 class="font-pixel text-xs sm:text-sm text-blue-950 dark:text-cyan-300">RPG LEVELING & XP</h3>
                        <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                            <strong>Manfaat untukmu:</strong> Mengubah rutinitas mencatat jadi adiktif dan seru. Setiap kata yang kamu tulis memberimu XP dan menaikkan rank Scribe kamu mulai dari Novice hingga Mythic.
                        </p>
                    </div>

                    <!-- Card 3: Zen Mode Writing Benefit -->
                    <div class="arcade-card p-6 rounded-xl border-2 border-black dark:border-blue-900 bg-[var(--bg-surface)] shadow-[5px_5px_0px_#000] space-y-3 hover:-translate-y-1 transition-transform">
                        <div class="w-12 h-12 rounded-lg bg-purple-600 text-white border-2 border-black shadow-[2px_2px_0px_#000] flex items-center justify-center text-xl">
                            <i class="fa-solid fa-expand"></i>
                        </div>
                        <h3 class="font-pixel text-xs sm:text-sm text-blue-950 dark:text-cyan-300">DISTRACTION-FREE ZEN MODE</h3>
                        <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                            <strong>Apa yang bisa kamu lakukan:</strong> Masuk ke mode layar penuh tanpa gangguan dengan 1 klik. Semua menu disembunyikan agar kamu fokus menumpahkan seluruh idemu dengan jernih.
                        </p>
                    </div>

                    <!-- Card 4: Multi-Font Typography Benefit -->
                    <div class="arcade-card p-6 rounded-xl border-2 border-black dark:border-blue-900 bg-[var(--bg-surface)] shadow-[5px_5px_0px_#000] space-y-3 hover:-translate-y-1 transition-transform">
                        <div class="w-12 h-12 rounded-lg bg-cyan-600 text-white border-2 border-black shadow-[2px_2px_0px_#000] flex items-center justify-center text-xl">
                            <i class="fa-solid fa-font"></i>
                        </div>
                        <h3 class="font-pixel text-xs sm:text-sm text-blue-950 dark:text-cyan-300">MULTI-FONT RETRO STUDIO</h3>
                        <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                            <strong>Apa yang bisa kamu lakukan:</strong> Sesuaikan atmosfer membacamu kapan pun dengan 5 pilihan font: Pixel 8-bit, VT323 Arcade, Fira Code programmer, Sans modern, dan Serif klasik.
                        </p>
                    </div>

                    <!-- Card 5: Dark and Light Themes Benefit -->
                    <div class="arcade-card p-6 rounded-xl border-2 border-black dark:border-blue-900 bg-[var(--bg-surface)] shadow-[5px_5px_0px_#000] space-y-3 hover:-translate-y-1 transition-transform">
                        <div class="w-12 h-12 rounded-lg bg-slate-800 text-cyan-300 border-2 border-black shadow-[2px_2px_0px_#000] flex items-center justify-center text-xl">
                            <i class="fa-solid fa-circle-half-stroke"></i>
                        </div>
                        <h3 class="font-pixel text-xs sm:text-sm text-blue-950 dark:text-cyan-300">DUAL THEME & CRT FX</h3>
                        <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                            <strong>Manfaat untukmu:</strong> Bekerja berjam-jam tanpa mata lelah. Pilih mode Arcade Day yang cerah atau Cyberpunk Night yang teduh, dengan favicon pintar yang otomatis beradaptasi.
                        </p>
                    </div>

                    <!-- Card 6: Universal Export / Import Benefit -->
                    <div class="arcade-card p-6 rounded-xl border-2 border-black dark:border-blue-900 bg-[var(--bg-surface)] shadow-[5px_5px_0px_#000] space-y-3 hover:-translate-y-1 transition-transform">
                        <div class="w-12 h-12 rounded-lg bg-emerald-600 text-white border-2 border-black shadow-[2px_2px_0px_#000] flex items-center justify-center text-xl">
                            <i class="fa-solid fa-file-export"></i>
                        </div>
                        <h3 class="font-pixel text-xs sm:text-sm text-blue-950 dark:text-cyan-300">UNIVERSAL ZERO LOCK-IN</h3>
                        <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                            <strong>Apa yang bisa kamu lakukan:</strong> Tulisanmu adalah hak milikmu seutuhnya. Ekspor seluruh naskah ke format Markdown (.md), HTML web siap tayang, atau cadangan JSON kapan saja.
                        </p>
                    </div>

                </div>

            </div>
        </section>

        <!-- ============================================================================== -->
        <!-- 4. SIMULASI PROTEKSI CATATAN: BUKTIKAN TULISANMU SELALU AMAN -->
        <!-- ============================================================================== -->
        <section id="keamanan" class="py-16 sm:py-20 px-4 sm:px-6 bg-[var(--bg-surface-alt)] border-t-2 border-black dark:border-blue-900 font-mono">
            <div class="max-w-5xl mx-auto space-y-8">

                <div class="text-center space-y-2">
                    <div class="inline-flex items-center gap-1.5 px-3 py-0.5 rounded border border-blue-500/40 bg-blue-500/10 text-blue-600 dark:text-cyan-400 font-pixel text-[10px]">
                        <i class="fa-solid fa-shield-halved"></i> UJI PROTEKSI REAL-TIME
                    </div>
                    <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight">Simulasi Proteksi Catatan: Buktikan Tulisanmu Selalu Aman</h2>
                    <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-400 max-w-xl mx-auto">
                        Coba langsung di bawah ini bagaimana Notenux mendeteksi perubahan data tak terduga dan memulihkan naskahmu seketika. Naskah pentingmu tidak akan pernah hilang!
                    </p>
                </div>

                <!-- Interactive Data Protection Simulator Deck -->
                <div class="arcade-card rounded-2xl border-2 border-black dark:border-blue-700 bg-[var(--bg-surface)] shadow-[6px_6px_0px_#000] p-4 sm:p-6 space-y-5">
                    <div class="flex flex-col xs:flex-row items-start xs:items-center justify-between gap-3 border-b-2 border-black/10 dark:border-blue-900/60 pb-3">
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full bg-emerald-500 inline-block"></span>
                            <span class="font-pixel text-xs text-blue-950 dark:text-cyan-300">SIMULATOR KEAMANAN & PEMULIHAN NASKAH</span>
                        </div>
                        <div class="flex items-center gap-2 w-full xs:w-auto">
                            <button type="button" id="sim-corrupt-btn" class="arcade-btn flex-1 xs:flex-none text-center px-3 py-1.5 rounded text-xs bg-rose-600 hover:bg-rose-500 text-white font-bold" title="Simulasikan gangguan data tak sah pada Bagian Catatan #2">
                                <i class="fa-solid fa-bug mr-1"></i> Simulasikan Gangguan
                            </button>
                            <button type="button" id="sim-repair-btn" class="arcade-btn flex-1 xs:flex-none text-center px-3 py-1.5 rounded text-xs bg-emerald-600 hover:bg-emerald-500 text-white font-bold" title="Pulihkan naskah dan verifikasi keaslian seketika">
                                <i class="fa-solid fa-wrench mr-1"></i> Pulihkan & Verifikasi
                            </button>
                        </div>
                    </div>

                    <!-- Global Status Banner -->
                    <div id="sim-status-banner" class="p-3 rounded-xl border-2 border-emerald-500/60 bg-emerald-500/10 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2.5 text-xs">
                        <div class="flex items-center gap-2">
                            <i id="sim-status-icon" class="fa-solid fa-circle-check text-emerald-500 text-base"></i>
                            <span id="sim-status-text" class="font-bold text-emerald-600 dark:text-emerald-400">STATUS: Seluruh bagian catatan utuh sempurna. Keamanan tulisan 100% terverifikasi.</span>
                        </div>
                        <span id="sim-status-chip" class="px-2 py-0.5 rounded font-pixel text-[9px] bg-emerald-600 text-white shrink-0">AMAN 100%</span>
                    </div>

                    <!-- 3 Visual Notes blocks -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4 text-xs">
                        <!-- Block 1 -->
                        <div class="p-4 rounded-xl border-2 border-black/20 dark:border-blue-900 bg-[var(--bg-surface-alt)] space-y-2">
                            <div class="flex items-center justify-between text-[11px] font-bold">
                                <span class="text-blue-600 dark:text-cyan-400">BAGIAN 01 [JUDUL NASKAH]</span>
                                <span class="text-emerald-500 font-pixel text-[9px]">UTUH</span>
                            </div>
                            <div class="p-2 rounded bg-black/20 text-[11px] font-mono text-slate-700 dark:text-slate-300 truncate">
                                # Catatan Strategi & Rencana Masa Depan
                            </div>
                            <div class="text-[10px] text-slate-500 dark:text-slate-400 break-all font-mono">
                                Kunci Proteksi: <code class="text-cyan-500">7f83b1657ff1fc53...</code>
                            </div>
                        </div>

                        <!-- Block 2 (Target Simulasi) -->
                        <div id="sim-shard-2-box" class="p-4 rounded-xl border-2 border-black/20 dark:border-blue-900 bg-[var(--bg-surface-alt)] space-y-2 transition-colors">
                            <div class="flex items-center justify-between text-[11px] font-bold">
                                <span id="sim-shard-2-title" class="text-blue-600 dark:text-cyan-400">BAGIAN 02 [DAFTAR TUGAS]</span>
                                <span id="sim-shard-2-badge" class="text-emerald-500 font-pixel text-[9px]">UTUH</span>
                            </div>
                            <div id="sim-shard-2-content" class="p-2 rounded bg-black/20 text-[11px] font-mono text-slate-700 dark:text-slate-300 truncate">
                                - [x] Selesaikan draf naskah utama
                            </div>
                            <div class="text-[10px] text-slate-500 dark:text-slate-400 break-all font-mono">
                                Kunci Proteksi: <code id="sim-shard-2-hash" class="text-cyan-500">9b71d224bd62f378...</code>
                            </div>
                        </div>

                        <!-- Block 3 -->
                        <div class="p-4 rounded-xl border-2 border-black/20 dark:border-blue-900 bg-[var(--bg-surface-alt)] space-y-2 sm:col-span-2 lg:col-span-1">
                            <div class="flex items-center justify-between text-[11px] font-bold">
                                <span class="text-blue-600 dark:text-cyan-400">BAGIAN 03 [TABEL DATA]</span>
                                <span class="text-emerald-500 font-pixel text-[9px]">UTUH</span>
                            </div>
                            <div class="p-2 rounded bg-black/20 text-[11px] font-mono text-slate-700 dark:text-slate-300 truncate">
                                | Topik | Status | Terlindungi |
                            </div>
                            <div class="text-[10px] text-slate-500 dark:text-slate-400 break-all font-mono">
                                Kunci Proteksi: <code class="text-cyan-500">4a5b6c7d8e9f0123...</code>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3 User Benefit Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6 text-xs">
                    <div class="arcade-card p-5 rounded-xl border-2 border-black dark:border-blue-800 bg-[var(--bg-surface)] shadow-[4px_4px_0px_#000] space-y-2">
                        <div class="text-blue-600 dark:text-cyan-400 font-bold flex items-center gap-2">
                            <span class="w-6 h-6 rounded bg-blue-600 text-white flex items-center justify-center text-[10px] font-pixel">01</span>
                            <span>Bebas Risiko Kehilangan</span>
                        </div>
                        <p class="text-slate-600 dark:text-slate-300 leading-relaxed">
                            Tulisanmu dipelihara secara otomatis. Bahkan jika browser tiba-tiba tertutup, catatan pentingmu tetap aman dan dapat diakses kembali tanpa cela.
                        </p>
                    </div>

                    <div class="arcade-card p-5 rounded-xl border-2 border-black dark:border-blue-800 bg-[var(--bg-surface)] shadow-[4px_4px_0px_#000] space-y-2">
                        <div class="text-emerald-600 dark:text-emerald-400 font-bold flex items-center gap-2">
                            <span class="w-6 h-6 rounded bg-emerald-600 text-white flex items-center justify-center text-[10px] font-pixel">02</span>
                            <span>Respons Cepat Tanpa Lemot</span>
                        </div>
                        <p class="text-slate-600 dark:text-slate-300 leading-relaxed">
                            Miliki ribuan catatan panjang sekalipun, Studio Notenux tetap terbuka seketika dan halus saat diketik tanpa membebani memori perangkatmu.
                        </p>
                    </div>

                    <div class="arcade-card p-5 rounded-xl border-2 border-black dark:border-blue-800 bg-[var(--bg-surface)] shadow-[4px_4px_0px_#000] space-y-2 sm:col-span-2 lg:col-span-1">
                        <div class="text-purple-600 dark:text-purple-400 font-bold flex items-center gap-2">
                            <span class="w-6 h-6 rounded bg-purple-600 text-white flex items-center justify-center text-[10px] font-pixel">03</span>
                            <span>Pemulihan 1-Klik yang Tenang</span>
                        </div>
                        <p class="text-slate-600 dark:text-slate-300 leading-relaxed">
                            Kamu selalu memegang kendali penuh atas tulisanmu. Sistem otomatis memvalidasi keutuhan naskah sehingga apa yang kamu baca adalah persis seperti yang kamu ketik.
                        </p>
                    </div>
                </div>

            </div>
        </section>

        <!-- ============================================================================== -->
        <!-- 5. RPG SCRIBE RANK CODEX (Interactive Progression Ladder) -->
        <!-- ============================================================================== -->
        <section id="rank-codex" class="py-16 sm:py-20 px-4 sm:px-6 font-mono">
            <div class="max-w-5xl mx-auto space-y-8">

                <div class="text-center space-y-2">
                    <div class="inline-flex items-center gap-1.5 px-3 py-0.5 rounded border border-amber-500/40 bg-amber-500/10 text-amber-600 dark:text-yellow-400 font-pixel text-[10px]">
                        <i class="fa-solid fa-crown"></i> PROGRESSION CODEX
                    </div>
                    <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight">Tangga Hirarki Scribe Notenux</h2>
                    <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-400 max-w-xl mx-auto">
                        Setiap kata, paragraf, dan checklist yang kamu selesaikan menambah EXP dan mana untuk menaikkan gelarmu.
                    </p>
                </div>

                <!-- 5 Rank Buttons Row -->
                <div class="grid grid-cols-2 xs:grid-cols-3 sm:grid-cols-5 gap-2 sm:gap-3">
                    <button type="button" class="rank-card arcade-btn p-3 rounded-xl border-2 border-black dark:border-blue-800 bg-blue-600 text-white text-center space-y-1" data-rank="1">
                        <i class="fa-solid fa-feather text-lg"></i>
                        <div class="font-pixel text-[9px]">LV. 1-9</div>
                        <div class="text-[10px] font-bold">Novice Scribe</div>
                    </button>

                    <button type="button" class="rank-card arcade-btn p-3 rounded-xl border-2 border-black dark:border-blue-800 bg-[var(--bg-surface)] text-[var(--text-main)] text-center space-y-1" data-rank="2">
                        <i class="fa-solid fa-scroll text-lg text-emerald-400"></i>
                        <div class="font-pixel text-[9px]">LV. 10-24</div>
                        <div class="text-[10px] font-bold">Apprentice</div>
                    </button>

                    <button type="button" class="rank-card arcade-btn p-3 rounded-xl border-2 border-black dark:border-blue-800 bg-[var(--bg-surface)] text-[var(--text-main)] text-center space-y-1" data-rank="3">
                        <i class="fa-solid fa-book-skull text-lg text-cyan-400"></i>
                        <div class="font-pixel text-[9px]">LV. 25-39</div>
                        <div class="text-[10px] font-bold">Scholar Scribe</div>
                    </button>

                    <button type="button" class="rank-card arcade-btn p-3 rounded-xl border-2 border-black dark:border-blue-800 bg-[var(--bg-surface)] text-[var(--text-main)] text-center space-y-1" data-rank="4">
                        <i class="fa-solid fa-hat-wizard text-lg text-purple-400"></i>
                        <div class="font-pixel text-[9px]">LV. 40-49</div>
                        <div class="text-[10px] font-bold">Archmage</div>
                    </button>

                    <button type="button" class="rank-card arcade-btn p-3 rounded-xl border-2 border-black dark:border-blue-800 bg-[var(--bg-surface)] text-[var(--text-main)] text-center space-y-1 col-span-2 xs:col-span-1" data-rank="5">
                        <i class="fa-solid fa-crown text-lg text-amber-400"></i>
                        <div class="font-pixel text-[9px]">LV. 50+</div>
                        <div class="text-[10px] font-bold">Chrono-Scribe</div>
                    </button>
                </div>

                <!-- Active Rank Detail Display Card -->
                <div id="rank-detail-card" class="arcade-card rounded-2xl border-2 border-black dark:border-blue-700 bg-[var(--bg-surface)] shadow-[6px_6px_0px_#000] p-4 sm:p-6 space-y-3">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 border-b-2 border-black/10 dark:border-blue-900/60 pb-3">
                        <div class="flex items-center gap-3">
                            <div id="rank-detail-icon" class="w-10 h-10 rounded-xl bg-blue-600 text-white flex items-center justify-center text-xl shadow-[2px_2px_0px_#000]">
                                <i class="fa-solid fa-feather"></i>
                            </div>
                            <div>
                                <h4 id="rank-detail-name" class="font-pixel text-xs sm:text-sm text-blue-950 dark:text-cyan-300">RANK: NOVICE SCRIBE</h4>
                                <p id="rank-detail-level" class="text-[11px] text-slate-500 dark:text-slate-400">Tingkat Kemahiran: Level 1 – 9 (0 – 450 XP)</p>
                            </div>
                        </div>
                        <span id="rank-detail-badge" class="px-3 py-1 rounded-full text-[10px] font-pixel bg-blue-100 dark:bg-blue-950 text-blue-800 dark:text-cyan-300 border border-blue-400/50">
                            INITIAL RANK
                        </span>
                    </div>

                    <p id="rank-detail-desc" class="text-xs sm:text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                        Langkah awal sang petualang aksara. Scribe pemula mempelajari seni mencatat naskah dengan Markdown dasar dan pengelompokan kategori yang terstruktur rapi.
                    </p>

                    <div class="pt-2">
                        <div class="text-[11px] font-bold text-slate-600 dark:text-slate-400 mb-2">MANFAAT & KEMAMPUAN YANG TERBUKA:</div>
                        <div id="rank-detail-perks" class="grid grid-cols-1 sm:grid-cols-3 gap-2 text-xs">
                            <div class="p-2.5 rounded-lg border border-black/15 dark:border-blue-900 bg-[var(--bg-surface-alt)] flex items-center gap-2">
                                <i class="fa-solid fa-check text-emerald-500"></i>
                                <span>Editor Markdown Dual-Mode</span>
                            </div>
                            <div class="p-2.5 rounded-lg border border-black/15 dark:border-blue-900 bg-[var(--bg-surface-alt)] flex items-center gap-2">
                                <i class="fa-solid fa-check text-emerald-500"></i>
                                <span>Proteksi Tulisan Otomatis</span>
                            </div>
                            <div class="p-2.5 rounded-lg border border-black/15 dark:border-blue-900 bg-[var(--bg-surface-alt)] flex items-center gap-2">
                                <i class="fa-solid fa-check text-emerald-500"></i>
                                <span>5 Tipografi Font Retro</span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </section>

        <!-- ============================================================================== -->
        <!-- 6. INTERACTIVE FAQ ACCORDION WITH LIVE SEARCH -->
        <!-- ============================================================================== -->
        <section id="faq" class="py-16 sm:py-20 px-4 sm:px-6 bg-[var(--bg-surface-alt)] border-t-2 border-black dark:border-blue-900 font-mono">
            <div class="max-w-4xl mx-auto space-y-8">

                <div class="text-center space-y-2">
                    <div class="inline-flex items-center gap-1.5 px-3 py-0.5 rounded border border-blue-500/40 bg-blue-500/10 text-blue-600 dark:text-cyan-400 font-pixel text-[10px]">
                        <i class="fa-solid fa-circle-question"></i> PERTANYAAN UMUM
                    </div>
                    <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight">Kerap Ditanyakan Pengguna</h2>
                    <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-400 max-w-lg mx-auto">
                        Cari jawaban cepat mengenai manfaat, privasi data, dan kemudahan yang kamu dapatkan di Notenux.
                    </p>
                </div>

                <!-- FAQ Search Box -->
                <div class="relative max-w-md mx-auto">
                    <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <input type="text"
                           id="faq-search-input"
                           placeholder="Cari topik FAQ (misal: login, export, tema, keamanan)..."
                           class="w-full pl-9 pr-4 py-2.5 rounded-xl border-2 border-black dark:border-blue-800 bg-[var(--bg-surface)] text-xs font-mono outline-none focus:border-cyan-400 shadow-[3px_3px_0px_#000]">
                </div>

                <div class="space-y-3" id="faq-list-container">
                    <!-- FAQ 1 -->
                    <div class="faq-item arcade-card rounded-xl border-2 border-black dark:border-blue-900 bg-[var(--bg-surface)] shadow-[3px_3px_0px_#000] overflow-hidden" data-keywords="login masuk akun studio registrasi privasi auth manfaat">
                        <button type="button" class="faq-trigger w-full px-4 sm:px-5 py-3 sm:py-4 text-left font-bold text-xs sm:text-sm flex items-center justify-between gap-4 cursor-pointer">
                            <span class="flex items-center gap-2 text-blue-950 dark:text-cyan-300">
                                <i class="fa-solid fa-lock text-blue-500"></i> Mengapa saya perlu login untuk membuka Studio Notenux?
                            </span>
                            <i class="fa-solid fa-chevron-down faq-chevron transition-transform duration-200"></i>
                        </button>
                        <div class="faq-content hidden px-4 sm:px-5 pb-4 text-xs text-slate-600 dark:text-slate-300 leading-relaxed border-t border-dashed border-black/10 dark:border-white/10 pt-3">
                            Setiap naskah dokumen, kategori, dan progres level XP RPG tersimpan aman di akun pribadimu. Dengan login, semua tulisanmu dijamin terlindungi, tersinkronisasi rapi, dan tidak dapat diintip atau tercampur dengan data pengguna lain. Jika belum punya akun, kamu dapat mendaftar gratis hanya dalam hitungan detik!
                        </div>
                    </div>

                    <!-- FAQ 2 -->
                    <div class="faq-item arcade-card rounded-xl border-2 border-black dark:border-blue-900 bg-[var(--bg-surface)] shadow-[3px_3px_0px_#000] overflow-hidden" data-keywords="tema dark light night day crt favicon ikon adaptif tampilan">
                        <button type="button" class="faq-trigger w-full px-4 sm:px-5 py-3 sm:py-4 text-left font-bold text-xs sm:text-sm flex items-center justify-between gap-4 cursor-pointer">
                            <span class="flex items-center gap-2 text-blue-950 dark:text-cyan-300">
                                <i class="fa-solid fa-circle-half-stroke text-amber-500"></i> Bagaimana cara kerja Dark/Light theme & Favicon adaptif?
                            </span>
                            <i class="fa-solid fa-chevron-down faq-chevron transition-transform duration-200"></i>
                        </button>
                        <div class="faq-content hidden px-4 sm:px-5 pb-4 text-xs text-slate-600 dark:text-slate-300 leading-relaxed border-t border-dashed border-black/10 dark:border-white/10 pt-3">
                            Kamu dapat mengklik tombol tema di navbar kapan saja untuk berganti antara mode Arcade Day (terang) dan Cyberpunk Night (gelap). Favicon Notenux menggunakan favicon light secara default, namun secara otomatis beradaptasi menjadi favicon dark saat kamu menggunakan tema gelap. Preferensi kamu tersimpan otomatis di browser sehingga selalu nyaman di matamu.
                        </div>
                    </div>

                    <!-- FAQ 3 -->
                    <div class="faq-item arcade-card rounded-xl border-2 border-black dark:border-blue-900 bg-[var(--bg-surface)] shadow-[3px_3px_0px_#000] overflow-hidden" data-keywords="ekspor unduh import markdown json backup lockin simpan file manfaat">
                        <button type="button" class="faq-trigger w-full px-4 sm:px-5 py-3 sm:py-4 text-left font-bold text-xs sm:text-sm flex items-center justify-between gap-4 cursor-pointer">
                            <span class="flex items-center gap-2 text-blue-950 dark:text-cyan-300">
                                <i class="fa-solid fa-file-arrow-down text-emerald-500"></i> Apakah saya bisa mengekspor atau membackup catatan saya keluar?
                            </span>
                            <i class="fa-solid fa-chevron-down faq-chevron transition-transform duration-200"></i>
                        </button>
                        <div class="faq-content hidden px-4 sm:px-5 pb-4 text-xs text-slate-600 dark:text-slate-300 leading-relaxed border-t border-dashed border-black/10 dark:border-white/10 pt-3">
                            Tentu saja! Notenux memegang teguh prinsip Zero Lock-In. Kamu dapat mengekspor seluruh naskah tulisanmu dalam sekejap ke berkas Markdown standar (.md), HTML web siap tayang, teks polos (.txt), maupun arsip cadangan JSON lengkap. Kamu juga dapat mengimpor kembali berkas Markdown kapan saja.
                        </div>
                    </div>

                    <!-- FAQ 4 -->
                    <div class="faq-item arcade-card rounded-xl border-2 border-black dark:border-blue-900 bg-[var(--bg-surface)] shadow-[3px_3px_0px_#000] overflow-hidden" data-keywords="keamanan proteksi data aman tidak hilang rusak pemulihan simpan">
                        <button type="button" class="faq-trigger w-full px-4 sm:px-5 py-3 sm:py-4 text-left font-bold text-xs sm:text-sm flex items-center justify-between gap-4 cursor-pointer">
                            <span class="flex items-center gap-2 text-blue-950 dark:text-cyan-300">
                                <i class="fa-solid fa-shield-halved text-purple-500"></i> Bagaimana Notenux menjamin catatan saya tidak rusak atau hilang?
                            </span>
                            <i class="fa-solid fa-chevron-down faq-chevron transition-transform duration-200"></i>
                        </button>
                        <div class="faq-content hidden px-4 sm:px-5 pb-4 text-xs text-slate-600 dark:text-slate-300 leading-relaxed border-t border-dashed border-black/10 dark:border-white/10 pt-3">
                            Sistem Notenux menerapkan mekanisme perlindungan keutuhan data secara otomatis. Setiap kali kamu menulis, tulisanmu dikunci dan diverifikasi secara cerdas. Jika terjadi ketidaksesuaian data, sistem dapat mendeteksinya secara langsung dan memulihkan naskahmu ke kondisi utuh tanpa kehilangan sepatah kata pun.
                        </div>
                    </div>
                </div>

                <div id="faq-empty-state" class="hidden p-6 rounded-xl border-2 border-dashed border-black/20 dark:border-blue-900 text-center text-xs text-slate-500 dark:text-slate-400">
                    <i class="fa-solid fa-circle-question text-2xl mb-2 text-amber-500 block"></i>
                    Tidak ada FAQ yang cocok dengan kata kunci tersebut. Coba kata kunci lain atau langsung masuki Studio!
                </div>

            </div>
        </section>

        <!-- ============================================================================== -->
        <!-- 7. FINAL CALL TO ACTION -->
        <!-- ============================================================================== -->
        <section class="py-16 sm:py-20 px-4 sm:px-6 bg-[var(--bg-surface)] border-t-2 border-black dark:border-blue-900 text-center font-mono relative overflow-hidden">
            <div class="absolute inset-0 bg-blue-600/5 dark:bg-cyan-500/5 pointer-events-none"></div>
            <div class="max-w-3xl mx-auto space-y-6 relative z-10">
                <i class="fa-solid fa-hat-wizard text-4xl text-blue-600 dark:text-cyan-400 animate-pulse"></i>
                <h2 class="text-2xl sm:text-4xl font-extrabold tracking-tight">Siap Memulai Petualangan Menulismu?</h2>
                <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                    Masuki Studio Notenux sekarang, nikmati editor Markdown bertenaga, rasakan perlindungan naskah otomatis, dan raih rank tertinggi sebagai Scribe legendaris.
                </p>
                <div class="pt-4 flex flex-col xs:flex-row gap-3 sm:gap-4 justify-center items-center">
                    <a href="{{ route('studio') }}"
                       id="bottom-studio-cta"
                       class="arcade-btn w-full xs:w-auto text-center px-8 py-4 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-pixel text-xs sm:text-sm tracking-wider shadow-[5px_5px_0px_#000] active:translate-y-1 transition-all">
                        <i class="fa-solid fa-terminal mr-2 text-cyan-300"></i>
                        <span>BUKA STUDIO NOTENUX</span>
                    </a>

                    @guest
                        <a href="{{ route('register') }}"
                           id="bottom-register-cta"
                           class="arcade-btn w-full xs:w-auto text-center px-8 py-4 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs sm:text-sm tracking-wider shadow-[5px_5px_0px_#000] active:translate-y-1 transition-all">
                            <i class="fa-solid fa-user-plus mr-2"></i>
                            <span>REGISTER AKUN BARU</span>
                        </a>
                    @endguest
                </div>
            </div>
        </section>

    </main>

    <!-- Floating Scribe Companion Widget (Mascot Sprite) -->
    <div id="scribe-companion" class="fixed bottom-3 right-3 sm:bottom-4 sm:right-4 z-40 font-mono hidden sm:flex items-end gap-2 pointer-events-auto max-w-[calc(100vw-2rem)]">
        <div id="companion-bubble" class="arcade-card p-3 rounded-xl border-2 border-black dark:border-blue-700 bg-[var(--bg-surface)] shadow-[4px_4px_0px_#000] text-xs max-w-[220px] transition-all">
            <div class="flex items-center justify-between text-[10px] text-slate-400 mb-1">
                <span class="font-pixel text-cyan-400">SCRIBE SPRITE</span>
                <button type="button" id="companion-close-btn" class="hover:text-rose-500"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <p id="companion-text" class="text-[11px] leading-snug text-slate-700 dark:text-slate-300">
                Tekan tombol template atau ketik di Playground untuk menaikkan XP Scribe kamu!
            </p>
        </div>
        <button type="button" id="companion-avatar-btn" class="w-11 h-11 sm:w-12 sm:h-12 rounded-2xl bg-amber-400 hover:bg-amber-300 text-black border-2 border-black shadow-[3px_3px_0px_#000] flex items-center justify-center text-lg sm:text-xl active:translate-y-0.5 transition-transform" title="Klik untuk tips Scribe!">
            🧙‍♂️
        </button>
    </div>

    <!-- Footer -->
    <footer class="w-full bg-[var(--bg-surface)] border-t-2 border-black dark:border-blue-900 py-6 px-4 sm:px-6 font-mono text-xs">
        <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-2">
                <img src="{{ asset('assets/logo/dark/icon_dark.png') }}" alt="Notenux Icon" class="w-5 h-5 object-contain hidden dark:inline-block">
                <img src="{{ asset('assets/logo/light/icon_light.png') }}" alt="Notenux Icon" class="w-5 h-5 object-contain inline-block dark:hidden">
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

    <!-- Interactive Landing Page Scripts (Playground, Station, Safety Check, Simulator, Codex, Audio) -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {

            // Audio Helper (integrates with window.notenux.audio or fallback)
            const playSound = (type = 'blip') => {
                if (window.notenux && window.notenux.audio) {
                    if (type === 'coin') window.notenux.audio.playCoin();
                    else if (type === 'levelup') window.notenux.audio.playLevelUp();
                    else if (type === 'warp') window.notenux.audio.playWarp();
                    else if (type === 'delete') window.notenux.audio.playDelete();
                    else window.notenux.audio.playBlip();
                }
            };

            // --------------------------------------------------------------------------
            // 1. FAQ Accordions & Live Search Filter
            // --------------------------------------------------------------------------
            document.querySelectorAll('.faq-trigger').forEach(trigger => {
                trigger.addEventListener('click', () => {
                    playSound('blip');
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

            const faqSearchInput = document.getElementById('faq-search-input');
            const faqItems = document.querySelectorAll('.faq-item');
            const faqEmptyState = document.getElementById('faq-empty-state');

            if (faqSearchInput) {
                faqSearchInput.addEventListener('input', () => {
                    const query = faqSearchInput.value.toLowerCase().trim();
                    let matchCount = 0;

                    faqItems.forEach(item => {
                        const text = item.textContent.toLowerCase();
                        const keywords = (item.getAttribute('data-keywords') || '').toLowerCase();
                        const matches = text.includes(query) || keywords.includes(query);

                        if (matches) {
                            item.classList.remove('hidden');
                            matchCount++;
                        } else {
                            item.classList.add('hidden');
                        }
                    });

                    if (faqEmptyState) {
                        if (matchCount === 0) faqEmptyState.classList.remove('hidden');
                        else faqEmptyState.classList.add('hidden');
                    }
                });
            }

            // --------------------------------------------------------------------------
            // 2. Hero Interactive Station (Tabs, Quests, Safety Check, Font Lab)
            // --------------------------------------------------------------------------
            const stationTabs = document.querySelectorAll('.station-tab-btn');
            const stationPanels = document.querySelectorAll('.station-panel');

            stationTabs.forEach(btn => {
                btn.addEventListener('click', () => {
                    playSound('blip');
                    const tab = btn.getAttribute('data-tab');

                    stationTabs.forEach(b => {
                        b.classList.remove('bg-blue-600', 'text-white');
                        b.classList.add('bg-[var(--bg-surface)]', 'text-[var(--text-main)]');
                    });
                    btn.classList.add('bg-blue-600', 'text-white');
                    btn.classList.remove('bg-[var(--bg-surface)]', 'text-[var(--text-main)]');

                    stationPanels.forEach(panel => panel.classList.add('hidden'));
                    const targetPanel = document.getElementById(`station-panel-${tab}`);
                    if (targetPanel) targetPanel.classList.remove('hidden');
                });
            });

            // Hero Quests System
            let heroQuestXp = 25;
            const heroQuestXpBar = document.getElementById('hero-quest-xp-bar');
            const heroQuestXpText = document.getElementById('hero-quest-xp-text');
            const heroQuestRank = document.getElementById('hero-quest-rank');
            const heroLevelupBanner = document.getElementById('hero-levelup-banner');
            const heroResetQuestsBtn = document.getElementById('hero-reset-quests-btn');

            document.querySelectorAll('.quest-item').forEach(item => {
                item.addEventListener('click', () => {
                    const isDone = item.getAttribute('data-completed') === 'true';
                    if (isDone) return;

                    const xpAward = parseInt(item.getAttribute('data-xp') || '25', 10);
                    item.setAttribute('data-completed', 'true');

                    const icon = item.querySelector('.quest-check-icon');
                    const title = item.querySelector('.quest-title');
                    const badge = item.querySelector('.quest-badge');

                    if (icon) {
                        icon.className = 'fa-solid fa-circle-check text-emerald-500 text-sm quest-check-icon shrink-0';
                    }
                    if (title) {
                        title.classList.add('line-through', 'text-slate-500', 'dark:text-slate-400');
                    }
                    if (badge) {
                        badge.className = 'text-[10px] font-pixel text-emerald-500 font-bold quest-badge shrink-0';
                        badge.textContent = `+${xpAward} XP [DONE]`;
                    }

                    heroQuestXp = Math.min(100, heroQuestXp + xpAward);
                    if (heroQuestXpBar) heroQuestXpBar.style.width = `${heroQuestXp}%`;
                    if (heroQuestXpText) heroQuestXpText.textContent = `${heroQuestXp} / 100 XP`;

                    if (heroQuestXp >= 100) {
                        playSound('levelup');
                        if (heroQuestRank) heroQuestRank.textContent = 'LV. 2 APPRENTICE SCRIBE';
                        if (heroLevelupBanner) heroLevelupBanner.classList.remove('hidden');
                    } else {
                        playSound('coin');
                    }
                });
            });

            if (heroResetQuestsBtn) {
                heroResetQuestsBtn.addEventListener('click', () => {
                    playSound('warp');
                    heroQuestXp = 25;
                    if (heroQuestXpBar) heroQuestXpBar.style.width = '25%';
                    if (heroQuestXpText) heroQuestXpText.textContent = '25 / 100 XP';
                    if (heroQuestRank) heroQuestRank.textContent = 'LV. 1 NOVICE SCRIBE';
                    if (heroLevelupBanner) heroLevelupBanner.classList.add('hidden');

                    const q2 = document.getElementById('quest-item-2');
                    if (q2) {
                        q2.setAttribute('data-completed', 'false');
                        q2.querySelector('.quest-check-icon').className = 'fa-regular fa-circle text-slate-400 text-sm quest-check-icon shrink-0';
                        q2.querySelector('.quest-title').classList.remove('line-through', 'text-slate-500', 'dark:text-slate-400');
                        const b = q2.querySelector('.quest-badge');
                        b.className = 'text-[10px] font-pixel text-amber-500 font-bold quest-badge shrink-0';
                        b.textContent = '+25 XP';
                    }

                    const q3 = document.getElementById('quest-item-3');
                    if (q3) {
                        q3.setAttribute('data-completed', 'false');
                        q3.querySelector('.quest-check-icon').className = 'fa-regular fa-circle text-slate-400 text-sm quest-check-icon shrink-0';
                        q3.querySelector('.quest-title').classList.remove('line-through', 'text-slate-500', 'dark:text-slate-400');
                        const b = q3.querySelector('.quest-badge');
                        b.className = 'text-[10px] font-pixel text-cyan-400 font-bold quest-badge shrink-0';
                        b.textContent = '+50 XP';
                    }
                });
            }

            // Live Safety & Protection Hasher
            const hasherInput = document.getElementById('hasher-input');
            const hasherOutput = document.getElementById('hasher-hash-output');
            const hasherCharCount = document.getElementById('hasher-char-count');
            const hasherByteCount = document.getElementById('hasher-byte-count');
            const copyHashBtn = document.getElementById('copy-hash-btn');

            async function computeSha256(str) {
                const buffer = new TextEncoder().encode(str);
                const hashBuffer = await crypto.subtle.digest('SHA-256', buffer);
                const hashArray = Array.from(new Uint8Array(hashBuffer));
                return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
            }

            if (hasherInput && hasherOutput) {
                const updateHasher = async () => {
                    const text = hasherInput.value;
                    if (hasherCharCount) hasherCharCount.textContent = text.length;
                    if (hasherByteCount) hasherByteCount.textContent = `${new TextEncoder().encode(text).length} B`;
                    try {
                        const hash = await computeSha256(text);
                        hasherOutput.textContent = hash;
                    } catch (e) {
                        hasherOutput.textContent = 'protected_hash_verified';
                    }
                };

                hasherInput.addEventListener('input', updateHasher);
            }

            if (copyHashBtn && hasherOutput) {
                copyHashBtn.addEventListener('click', () => {
                    playSound('coin');
                    if (navigator.clipboard) {
                        navigator.clipboard.writeText(hasherOutput.textContent.trim());
                        copyHashBtn.innerHTML = '<i class="fa-solid fa-check mr-1"></i> Tersalin!';
                        setTimeout(() => {
                            copyHashBtn.innerHTML = '<i class="fa-solid fa-copy mr-1"></i> Salin Kunci';
                        }, 1800);
                    }
                });
            }

            // Hero Font Lab
            const heroFontPreviewBox = document.getElementById('hero-font-preview-box');
            document.querySelectorAll('.hero-font-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    playSound('warp');
                    const font = btn.getAttribute('data-font');
                    document.querySelectorAll('.hero-font-btn').forEach(b => {
                        b.classList.remove('bg-blue-600', 'text-white');
                        b.classList.add('bg-[var(--bg-surface)]', 'text-[var(--text-main)]');
                    });
                    btn.classList.add('bg-blue-600', 'text-white');
                    btn.classList.remove('bg-[var(--bg-surface)]', 'text-[var(--text-main)]');

                    if (heroFontPreviewBox) {
                        heroFontPreviewBox.classList.remove('font-pixel', 'font-arcade', 'font-mono', 'font-sans', 'font-serif');
                        if (font === 'pixel') heroFontPreviewBox.classList.add('font-pixel');
                        else if (font === 'arcade') heroFontPreviewBox.classList.add('font-arcade');
                        else if (font === 'mono') heroFontPreviewBox.classList.add('font-mono');
                        else if (font === 'serif') heroFontPreviewBox.classList.add('font-serif');
                        else heroFontPreviewBox.classList.add('font-sans');
                    }
                });
            });

            // --------------------------------------------------------------------------
            // 3. Interactive Demo Playground (Spell Bar, Metrics, Actions, Preview)
            // --------------------------------------------------------------------------
            const demoEditor = document.getElementById('demo-editor-input');
            const demoWordCount = document.getElementById('demo-word-count');
            const demoCharCount = document.getElementById('demo-char-count');
            const demoLineCount = document.getElementById('demo-line-count');
            const demoReadTime = document.getElementById('demo-read-time');
            const demoXpBar = document.getElementById('demo-xp-bar');
            const demoXpText = document.getElementById('demo-xp-text');
            const demoLevelDisplay = document.getElementById('demo-level-display');
            const demoPreviewContainer = document.getElementById('demo-preview-container');
            const demoEditorContainer = document.getElementById('demo-editor-container');
            const togglePreviewBtn = document.getElementById('toggle-preview-btn');
            const demoCopyBtn = document.getElementById('demo-copy-btn');
            const demoDownloadBtn = document.getElementById('demo-download-btn');

            let simulatedXp = 25;
            let simulatedLevel = 1;

            const updateDemoMetrics = () => {
                if (!demoEditor) return;
                const text = demoEditor.value || '';
                const words = text.trim() ? text.trim().split(/\s+/).length : 0;
                const chars = text.length;
                const lines = text ? text.split('\n').length : 0;
                const readMins = Math.max(1, Math.ceil(words / 200));

                if (demoWordCount) demoWordCount.textContent = words;
                if (demoCharCount) demoCharCount.textContent = chars;
                if (demoLineCount) demoLineCount.textContent = lines;
                if (demoReadTime) demoReadTime.textContent = `~${readMins} mnt baca`;
            };

            if (demoEditor) {
                demoEditor.addEventListener('input', () => {
                    updateDemoMetrics();

                    // XP Level Simulation on typing
                    simulatedXp += 2;
                    if (simulatedXp >= 100) {
                        simulatedLevel += 1;
                        simulatedXp = 10;
                        playSound('levelup');
                    }

                    if (demoXpBar) demoXpBar.style.width = Math.min(simulatedXp, 100) + '%';
                    if (demoXpText) demoXpText.textContent = `${simulatedXp} / 100 XP`;
                    if (demoLevelDisplay) demoLevelDisplay.textContent = `LV. ${simulatedLevel} ${simulatedLevel > 1 ? 'SCRIBE ADEPT' : 'NOVICE'}`;
                });
            }

            // Spell Bar Formatting
            document.querySelectorAll('.spell-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    if (!demoEditor) return;
                    playSound('blip');
                    const action = btn.getAttribute('data-action');
                    const start = demoEditor.selectionStart;
                    const end = demoEditor.selectionEnd;
                    const val = demoEditor.value;
                    const selected = val.substring(start, end);

                    let replacement = '';
                    if (action === 'bold') replacement = `**${selected || 'teks tebal'}**`;
                    else if (action === 'italic') replacement = `*${selected || 'teks miring'}*`;
                    else if (action === 'heading') replacement = `\n## ${selected || 'Judul Bagian Baru'}\n`;
                    else if (action === 'task') replacement = `\n- [ ] ${selected || 'Tugas baru'}\n`;
                    else if (action === 'code') replacement = `\n\`\`\`javascript\n${selected || '// Catatan kode'}\n\`\`\`\n`;
                    else if (action === 'quote') replacement = `\n> ${selected || 'Kutipan berharga'}\n`;

                    demoEditor.value = val.substring(0, start) + replacement + val.substring(end);
                    demoEditor.focus();
                    demoEditor.dispatchEvent(new Event('input'));
                });
            });

            // Copy & Download Actions
            if (demoCopyBtn && demoEditor) {
                demoCopyBtn.addEventListener('click', () => {
                    playSound('coin');
                    if (navigator.clipboard) {
                        navigator.clipboard.writeText(demoEditor.value);
                        demoCopyBtn.innerHTML = '<i class="fa-solid fa-check mr-1 text-emerald-400"></i> Tersalin!';
                        setTimeout(() => {
                            demoCopyBtn.innerHTML = '<i class="fa-solid fa-copy mr-1 text-cyan-500"></i> Salin';
                        }, 1800);
                    }
                });
            }

            if (demoDownloadBtn && demoEditor) {
                demoDownloadBtn.addEventListener('click', () => {
                    playSound('coin');
                    const blob = new Blob([demoEditor.value], { type: 'text/markdown;charset=utf-8' });
                    const url = URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    link.href = url;
                    link.download = 'notenux-manuscript.md';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    URL.revokeObjectURL(url);
                });
            }

            // Font Selector in Demo
            document.querySelectorAll('.demo-font-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    playSound('warp');
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
            const templateTableBtn = document.getElementById('template-table-btn');

            if (templateQuestBtn && demoEditor) {
                templateQuestBtn.addEventListener('click', () => {
                    playSound('coin');
                    demoEditor.value = `# ⚔️ Rencana Harian: Petualangan Menulis

- [x] Tulis draf naskah utama
- [x] Susun prioritas tugas mingguan
- [ ] Buka mode Zen layar penuh
- [ ] Simpan dan backup naskah ke Markdown!`;
                    demoEditor.dispatchEvent(new Event('input'));
                });
            }

            if (templateCodeBtn && demoEditor) {
                templateCodeBtn.addEventListener('click', () => {
                    playSound('coin');
                    demoEditor.value = `# 💻 Catatan Kode: Snippet Cepat

\`\`\`javascript
// Simpan dan olah data dengan cepat
const saveNote = (note) => {
    return { status: 'success', savedAt: new Date() };
};
\`\`\`

Catatan kode rapi dan siap diekspor kapan saja!`;
                    demoEditor.dispatchEvent(new Event('input'));
                });
            }

            if (templateTableBtn && demoEditor) {
                templateTableBtn.addEventListener('click', () => {
                    playSound('coin');
                    demoEditor.value = `# 📊 Matriks Produktivitas Mingguan

| Hari | Target Tulisan | Status |
| :--- | :--- | :--- |
| Senin | 500 kata & Outline | Selesai |
| Selasa | Bab 1 Naskah | Selesai |
| Rabu | Review & Pratinjau | Terjadwal |`;
                    demoEditor.dispatchEvent(new Event('input'));
                });
            }

            // Toggle Preview
            if (togglePreviewBtn && demoPreviewContainer && demoEditorContainer && demoEditor) {
                togglePreviewBtn.addEventListener('click', () => {
                    playSound('blip');
                    const isPreview = !demoPreviewContainer.classList.contains('hidden');
                    if (isPreview) {
                        demoPreviewContainer.classList.add('hidden');
                        demoEditorContainer.classList.remove('hidden');
                        togglePreviewBtn.innerHTML = '👁️ Preview';
                    } else {
                        // Render basic markdown into preview
                        const raw = demoEditor.value;
                        const rendered = raw
                            .replace(/^# (.*$)/gim, '<h2 class="text-xl font-bold text-blue-500 mb-2 font-pixel">$1</h2>')
                            .replace(/^## (.*$)/gim, '<h3 class="text-lg font-bold text-cyan-400 mb-1.5 font-pixel">$1</h3>')
                            .replace(/^- \[x\] (.*$)/gim, '<div class="text-emerald-500 font-mono py-0.5">✅ $1</div>')
                            .replace(/^- \[ \] (.*$)/gim, '<div class="text-slate-400 font-mono py-0.5">⬜ $1</div>')
                            .replace(/```([\s\S]*?)```/gm, '<pre class="bg-black/30 p-2.5 rounded font-mono text-xs my-2 border border-blue-500/30 overflow-x-auto">$1</pre>')
                            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                            .replace(/\*(.*?)\*/g, '<em>$1</em>')
                            .replace(/\n/gim, '<br>');
                        demoPreviewContainer.innerHTML = rendered;
                        demoPreviewContainer.classList.remove('hidden');
                        demoEditorContainer.classList.add('hidden');
                        togglePreviewBtn.innerHTML = '✏️ Edit';
                    }
                });
            }

            // --------------------------------------------------------------------------
            // 4. Interactive Protection & Recovery Simulator
            // --------------------------------------------------------------------------
            const simCorruptBtn = document.getElementById('sim-corrupt-btn');
            const simRepairBtn = document.getElementById('sim-repair-btn');
            const simStatusBanner = document.getElementById('sim-status-banner');
            const simStatusIcon = document.getElementById('sim-status-icon');
            const simStatusText = document.getElementById('sim-status-text');
            const simStatusChip = document.getElementById('sim-status-chip');
            const simShard2Box = document.getElementById('sim-shard-2-box');
            const simShard2Badge = document.getElementById('sim-shard-2-badge');
            const simShard2Content = document.getElementById('sim-shard-2-content');
            const simShard2Hash = document.getElementById('sim-shard-2-hash');

            if (simCorruptBtn && simRepairBtn) {
                simCorruptBtn.addEventListener('click', () => {
                    playSound('delete');

                    if (simStatusBanner) {
                        simStatusBanner.className = 'p-3 rounded-xl border-2 border-rose-500/80 bg-rose-500/15 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2.5 text-xs animate-pulse';
                    }
                    if (simStatusIcon) {
                        simStatusIcon.className = 'fa-solid fa-triangle-exclamation text-rose-500 text-base';
                    }
                    if (simStatusText) {
                        simStatusText.className = 'font-bold text-rose-600 dark:text-rose-400';
                        simStatusText.textContent = 'PERINGATAN: Perubahan tak terduga terdeteksi pada Bagian #02! Sistem otomatis siap memulihkan.';
                    }
                    if (simStatusChip) {
                        simStatusChip.className = 'px-2 py-0.5 rounded font-pixel text-[9px] bg-rose-600 text-white shrink-0';
                        simStatusChip.textContent = 'TERDETEKSI';
                    }

                    if (simShard2Box) {
                        simShard2Box.classList.add('border-rose-500', 'bg-rose-500/10');
                    }
                    if (simShard2Badge) {
                        simShard2Badge.className = 'text-rose-500 font-pixel text-[9px] animate-pulse';
                        simShard2Badge.textContent = 'TERUBAH!';
                    }
                    if (simShard2Content) {
                        simShard2Content.textContent = '⚠️ [DATA TERGANGGU - TEKAN PULIHKAN]';
                        simShard2Content.classList.add('text-rose-500');
                    }
                    if (simShard2Hash) {
                        simShard2Hash.textContent = 'e02a9c1d0f84a0c8...';
                        simShard2Hash.className = 'text-rose-400 font-bold';
                    }
                });

                simRepairBtn.addEventListener('click', () => {
                    playSound('levelup');

                    if (simStatusBanner) {
                        simStatusBanner.className = 'p-3 rounded-xl border-2 border-emerald-500/60 bg-emerald-500/10 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2.5 text-xs';
                    }
                    if (simStatusIcon) {
                        simStatusIcon.className = 'fa-solid fa-circle-check text-emerald-500 text-base';
                    }
                    if (simStatusText) {
                        simStatusText.className = 'font-bold text-emerald-600 dark:text-emerald-400';
                        simStatusText.textContent = 'STATUS: Seluruh bagian catatan utuh sempurna. Keamanan tulisan 100% terverifikasi.';
                    }
                    if (simStatusChip) {
                        simStatusChip.className = 'px-2 py-0.5 rounded font-pixel text-[9px] bg-emerald-600 text-white shrink-0';
                        simStatusChip.textContent = 'AMAN 100%';
                    }

                    if (simShard2Box) {
                        simShard2Box.classList.remove('border-rose-500', 'bg-rose-500/10');
                    }
                    if (simShard2Badge) {
                        simShard2Badge.className = 'text-emerald-500 font-pixel text-[9px]';
                        simShard2Badge.textContent = 'UTUH';
                    }
                    if (simShard2Content) {
                        simShard2Content.textContent = '- [x] Selesaikan draf naskah utama';
                        simShard2Content.classList.remove('text-rose-500');
                    }
                    if (simShard2Hash) {
                        simShard2Hash.textContent = '9b71d224bd62f378...';
                        simShard2Hash.className = 'text-cyan-500 font-bold';
                    }
                });
            }

            // --------------------------------------------------------------------------
            // 5. RPG Scribe Rank Codex Interactive Selector
            // --------------------------------------------------------------------------
            const rankData = {
                '1': {
                    name: 'RANK: NOVICE SCRIBE',
                    level: 'Tingkat Kemahiran: Level 1 – 9 (0 – 450 XP)',
                    badge: 'INITIAL RANK',
                    icon: '<i class="fa-solid fa-feather"></i>',
                    color: 'bg-blue-600',
                    desc: 'Langkah awal sang petualang aksara. Scribe pemula mempelajari seni mencatat naskah dengan Markdown dasar dan pengelompokan kategori yang terstruktur rapi.',
                    perks: ['Editor Markdown Dual-Mode', 'Proteksi Tulisan Otomatis', '5 Tipografi Font Retro']
                },
                '2': {
                    name: 'RANK: APPRENTICE SCRIBE',
                    level: 'Tingkat Kemahiran: Level 10 – 24 (450 – 1,200 XP)',
                    badge: 'UNLOCKED AT LV.10',
                    icon: '<i class="fa-solid fa-scroll text-emerald-400"></i>',
                    color: 'bg-emerald-600',
                    desc: 'Scribe telah menguasai dasar sintaks. Naskah catatan tersusun rapi dengan checklist interaktif dan alur penulisan cepat tanpa lag.',
                    perks: ['Manajemen Multi-Kategori', 'Ekspor HTML & Markdown 1-Klik', 'Shortcut Keyboard Cepat']
                },
                '3': {
                    name: 'RANK: SCHOLAR SCRIBE',
                    level: 'Tingkat Kemahiran: Level 25 – 39 (1,200 – 2,500 XP)',
                    badge: 'UNLOCKED AT LV.25',
                    icon: '<i class="fa-solid fa-book-skull text-cyan-400"></i>',
                    color: 'bg-cyan-600',
                    desc: 'Cendekiawan arsip digital. Membangun perpustakaan pengetahuan dengan struktur tabel markdown dan riwayat edit tak terbatas.',
                    perks: ['Dukungan Tabel Markdown', 'Pencarian Instan Sekejap', 'Notepad Plain Text Mode']
                },
                '4': {
                    name: 'RANK: ARCHMAGE SCRIBE',
                    level: 'Tingkat Kemahiran: Level 40 – 49 (2,500 – 4,000 XP)',
                    badge: 'UNLOCKED AT LV.40',
                    icon: '<i class="fa-solid fa-hat-wizard text-purple-400"></i>',
                    color: 'bg-purple-600',
                    desc: 'Penyihir aksara agung. Mampu mempertahankan fokus absolut dalam Zen Mode layar penuh dan manipulasi tata letak tingkat lanjut.',
                    perks: ['Distraction-Free Zen Mode', 'Audit Keamanan Naskah Manual', 'Pengurutan Naskah Fleksibel']
                },
                '5': {
                    name: 'RANK: MYTHIC CHRONO-SCRIBE',
                    level: 'Tingkat Kemahiran: Level 50+ (4,000+ XP)',
                    badge: 'MASTER TIER',
                    icon: '<i class="fa-solid fa-crown text-amber-400"></i>',
                    color: 'bg-amber-500',
                    desc: 'Gelar tertinggi di Notenux. Mengendalikan waktu dan catatan dengan cadangan JSON menyeluruh dan status keabadian data.',
                    perks: ['Cadangan JSON Menyeluruh', 'Insignia Mahkota Legendaris', 'Akses Prioritas Fitur AI Assistant']
                }
            };

            document.querySelectorAll('.rank-card').forEach(card => {
                card.addEventListener('click', () => {
                    playSound('warp');
                    const rankId = card.getAttribute('data-rank');
                    const data = rankData[rankId];
                    if (!data) return;

                    document.querySelectorAll('.rank-card').forEach(c => {
                        c.classList.remove('bg-blue-600', 'text-white');
                        c.classList.add('bg-[var(--bg-surface)]', 'text-[var(--text-main)]');
                    });
                    card.classList.add('bg-blue-600', 'text-white');
                    card.classList.remove('bg-[var(--bg-surface)]', 'text-[var(--text-main)]');

                    const nameEl = document.getElementById('rank-detail-name');
                    const levelEl = document.getElementById('rank-detail-level');
                    const badgeEl = document.getElementById('rank-detail-badge');
                    const iconEl = document.getElementById('rank-detail-icon');
                    const descEl = document.getElementById('rank-detail-desc');
                    const perksEl = document.getElementById('rank-detail-perks');

                    if (nameEl) nameEl.textContent = data.name;
                    if (levelEl) levelEl.textContent = data.level;
                    if (badgeEl) badgeEl.textContent = data.badge;
                    if (iconEl) {
                        iconEl.className = `w-10 h-10 rounded-xl ${data.color} text-white flex items-center justify-center text-xl shadow-[2px_2px_0px_#000]`;
                        iconEl.innerHTML = data.icon;
                    }
                    if (descEl) descEl.textContent = data.desc;
                    if (perksEl) {
                        perksEl.innerHTML = data.perks.map(p => `
                            <div class="p-2.5 rounded-lg border border-black/15 dark:border-blue-900 bg-[var(--bg-surface-alt)] flex items-center gap-2">
                                <i class="fa-solid fa-check text-emerald-500"></i>
                                <span>${p}</span>
                            </div>
                        `).join('');
                    }
                });
            });

            // --------------------------------------------------------------------------
            // 6. Floating Companion Sprite (Mascot Tips)
            // --------------------------------------------------------------------------
            const companionTips = [
                '💡 Tip: Di Studio, tekan Ctrl+S untuk menyimpan catatanmu seketika!',
                '⚔️ Selesaikan checklist di catatanmu untuk mendapatkan bonus XP!',
                '🛡️ Catatanmu selalu terlindungi secara otomatis tanpa takut hilang saat browser tertutup.',
                '🎨 Ganti tema Day / Night kapan saja — Favicon kami juga ikut berganti!',
                '📖 Mode Zen layar penuh menyembunyikan semua distraksi agar idemu mengalir.',
                '💾 Gunakan tombol Ekspor di Studio untuk backup catatan ke format Markdown (.md).'
            ];

            let companionIndex = 0;
            const companionBubble = document.getElementById('companion-bubble');
            const companionText = document.getElementById('companion-text');
            const companionAvatarBtn = document.getElementById('companion-avatar-btn');
            const companionCloseBtn = document.getElementById('companion-close-btn');

            if (companionAvatarBtn && companionText) {
                companionAvatarBtn.addEventListener('click', () => {
                    playSound('blip');
                    companionIndex = (companionIndex + 1) % companionTips.length;
                    companionText.textContent = companionTips[companionIndex];
                    if (companionBubble) companionBubble.classList.remove('hidden');
                });
            }

            if (companionCloseBtn && companionBubble) {
                companionCloseBtn.addEventListener('click', () => {
                    playSound('blip');
                    companionBubble.classList.add('hidden');
                });
            }

        });
    </script>

</body>
</html>
