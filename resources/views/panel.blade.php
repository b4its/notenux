<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Studio Metadata -->
    <title>Notenux Studio - Editor Catatan & Markdown Gamifikasi</title>
    <meta name="title" content="Notenux Studio - Editor Catatan & Markdown Gamifikasi">
    <meta name="description" content="Notenux Studio adalah editor naskah dan catatan retro modern dengan dukungan Markdown, mode mesin tik, dan gamifikasi rank Scribe.">
    <meta name="author" content="Oryphem & Notenux Team">
    <meta name="publisher" content="https://notenux.oryphem.com">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="googlebot" content="noindex, nofollow, noarchive">
    <meta name="bingbot" content="noindex, nofollow, noarchive">
    <link rel="canonical" href="https://notenux.oryphem.com/studio">

    <!-- Alternate Languages / Hreflang -->
    <link rel="alternate" hreflang="id" href="https://notenux.oryphem.com/studio">
    <link rel="alternate" hreflang="en" href="https://notenux.oryphem.com/studio">
    <link rel="alternate" hreflang="x-default" href="https://notenux.oryphem.com/studio">

    <!-- Mobile & PWA Settings -->
    <meta name="application-name" content="Notenux">
    <meta name="apple-mobile-web-app-title" content="Notenux">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="msapplication-TileColor" content="#030712">
    <meta name="msapplication-TileImage" content="{{ asset('assets/logo/light/icon_light.png') }}">
    <meta name="theme-color" content="#030712" media="(prefers-color-scheme: dark)">
    <meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">

    <!-- App Favicon & Touch Icon (Default Light, dynamically adapts to theme) -->
    <link rel="icon" id="app-favicon" type="image/png" href="{{ asset('assets/logo/light/icon_light.png') }}">
    <link rel="apple-touch-icon" id="app-apple-touch-icon" href="{{ asset('assets/logo/light/icon_light.png') }}">

    <!-- DNS-Prefetch and Preconnect -->
    <link rel="dns-prefetch" href="//fonts.googleapis.com">
    <link rel="dns-prefetch" href="//fonts.gstatic.com">
    <link rel="dns-prefetch" href="//cdnjs.cloudflare.com">

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

    <!-- Font Awesome 6 Free CDN for all icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <!-- Google Fonts for Retro Gamification & Multi-Font Typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;600&family=Instrument+Sans:wght@400;500;600;700&family=Merriweather:ital,wght@0,300;0,400;0,700;1,300&family=Press+Start+2P&family=VT323&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        /* Primary Nav Sidebar Collapse (Symmetric Icon Rail) */
        #primary-nav-sidebar.collapsed {
            width: 72px !important;
            min-width: 72px !important;
            max-width: 72px !important;
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
        }

        #primary-nav-sidebar.collapsed .nav-text-label,
        #primary-nav-sidebar.collapsed .nav-hide-on-collapse {
            display: none !important;
        }

        /* Symmetric Player Profile Card in Collapsed State */
        #primary-nav-sidebar.collapsed .player-profile-section {
            padding: 0.75rem 0.25rem !important;
            width: 100% !important;
            display: flex !important;
            justify-content: center !important;
            align-items: center !important;
        }

        #primary-nav-sidebar.collapsed .player-status-card {
            padding: 0 !important;
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
            width: auto !important;
            margin: 0 auto !important;
        }

        #primary-nav-sidebar.collapsed .player-avatar-row {
            margin-bottom: 0 !important;
            gap: 0 !important;
            justify-content: center !important;
        }

        #primary-nav-sidebar.collapsed .player-avatar-box {
            width: 44px !important;
            height: 44px !important;
            margin: 0 auto !important;
            border-radius: 10px !important;
        }

        #primary-nav-sidebar.collapsed .collapsed-level-badge {
            display: block !important;
        }

        /* Symmetric Navigation Links in Collapsed State */
        #primary-nav-sidebar.collapsed .primary-nav-links {
            padding: 0.75rem 0.25rem !important;
            width: 100% !important;
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
            gap: 0.35rem !important;
        }

        #primary-nav-sidebar.collapsed .collapsed-cat-header {
            display: flex !important;
            justify-content: center !important;
            width: 100% !important;
            margin: 0.25rem 0 !important;
        }

        #primary-nav-sidebar.collapsed #sidebar-categories-list {
            width: 100% !important;
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
            gap: 0.35rem !important;
        }

        #primary-nav-sidebar.collapsed .nav-category-item {
            width: 100% !important;
            display: flex !important;
            justify-content: center !important;
            align-items: center !important;
            padding: 0 !important;
            margin: 0 !important;
        }

        #primary-nav-sidebar.collapsed .nav-category-link {
            width: 44px !important;
            height: 44px !important;
            min-width: 44px !important;
            max-width: 44px !important;
            padding: 0 !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            margin: 0 auto !important;
            gap: 0 !important;
            border-radius: 10px !important;
            border-width: 2px !important;
            transition: all 0.15s ease-in-out !important;
        }

        #primary-nav-sidebar.collapsed .nav-category-link i {
            width: auto !important;
            font-size: 1.1rem !important;
            text-align: center !important;
            margin: 0 !important;
            display: inline-block !important;
            line-height: 1 !important;
        }

        #primary-nav-sidebar.collapsed .nav-category-link.bg-blue-600 {
            background-color: #2563eb !important;
            color: #ffffff !important;
            border-color: #000000 !important;
            box-shadow: 2px 2px 0px #000000 !important;
        }

        #primary-nav-sidebar.collapsed .nav-category-link:not(.bg-blue-600) {
            border-color: transparent !important;
            box-shadow: none !important;
        }

        #primary-nav-sidebar.collapsed .nav-category-link:not(.bg-blue-600):hover {
            border-color: rgba(0, 0, 0, 0.3) !important;
            background-color: var(--bg-surface-alt) !important;
            transform: scale(1.05);
        }

        #primary-nav-sidebar.collapsed .sidebar-edit-category-btn,
        #primary-nav-sidebar.collapsed .sidebar-delete-category-btn {
            display: none !important;
        }

        /* Notes Explorer Sidebar Collapse */
        #notes-explorer-sidebar.collapsed {
            width: 0px !important;
            min-width: 0px !important;
            padding: 0px !important;
            margin: 0px !important;
            border-right: none !important;
            overflow: hidden !important;
            opacity: 0;
            pointer-events: none;
        }

        /* Zen Mode (Distraction-free) */
        body.zen-mode #primary-nav-sidebar,
        body.zen-mode #notes-explorer-sidebar,
        body.zen-mode header {
            display: none !important;
        }
        body.zen-mode main {
            padding: 1.5rem !important;
        }
    </style>
</head>
<body class="retro-crt-screen bg-[var(--bg-main)] text-[var(--text-main)] h-screen h-dvh font-sans flex flex-col antialiased selection:bg-blue-600 selection:text-white overflow-hidden">

    <!-- Top-Right Retro Notification Toast Container -->
    <div id="toast-container" class="fixed top-4 right-4 z-50 flex flex-col gap-2 max-w-sm w-full pointer-events-none"></div>

    <!-- Top Navigation Bar (Retro Console Header) -->
    <header class="shrink-0 bg-[var(--bg-surface)] border-b-2 border-black dark:border-blue-900 shadow-[0_4px_0_rgba(0,0,0,0.25)] px-2 sm:px-4 lg:px-6 py-2 sm:py-2.5 z-30">
        <div class="flex items-center justify-between gap-1.5 sm:gap-4 max-w-7xl mx-auto">
            <!-- Left Branding & Dual-Sidebar Mobile Triggers -->
            <div class="flex items-center gap-1 sm:gap-2.5 min-w-0">
                <!-- Mobile Primary Nav Toggle -->
                <button id="mobile-nav-toggle-btn"
                        class="lg:hidden arcade-btn bg-blue-600 text-white p-1.5 sm:p-2 rounded text-xs shrink-0 flex items-center justify-center w-8 h-8 sm:w-auto sm:h-auto"
                        aria-label="Toggle Primary Navigation Drawer">
                    <i class="fa-solid fa-bars"></i>
                </button>

                <!-- Mobile Scroll Explorer Toggle -->
                <button id="mobile-explorer-toggle-btn"
                        class="lg:hidden arcade-btn bg-cyan-600 text-white p-1.5 sm:p-2 rounded text-xs shrink-0 flex items-center justify-center w-8 h-8 sm:w-auto sm:h-auto"
                        aria-label="Toggle Scroll Explorer Drawer">
                    <i class="fa-solid fa-folder-open"></i>
                </button>

                <!-- Desktop Primary Sidebar Toggle -->
                <button id="primary-sidebar-toggle-btn"
                        class="hidden lg:inline-flex arcade-btn bg-blue-600 hover:bg-blue-500 text-white p-2 rounded text-xs shrink-0"
                        title="Toggle Primary Navigation Sidebar">
                    <i class="fa-solid fa-arrows-left-right-to-line"></i>
                </button>

                <!-- Desktop Notes Explorer Toggle -->
                <button id="explorer-toggle-btn"
                        class="hidden lg:inline-flex arcade-btn bg-cyan-700 hover:bg-cyan-600 text-white p-2 rounded text-xs shrink-0"
                        title="Toggle Scroll Explorer Sidebar">
                    <i class="fa-solid fa-table-columns"></i>
                </button>

                <!-- Logo & Brand Badge -->
                <a href="{{ route('landing') }}" class="flex items-center gap-1.5 sm:gap-2.5 group transition-transform active:translate-y-0.5 shrink-0" title="Kembali ke Landing Page">
                    <!-- Adaptive Horizontal Logo -->
                    <img src="{{ asset('assets/logo/dark/icon_horizontal_dark.png') }}"
                         alt="Notenux Studio"
                         class="h-6 sm:h-8 w-auto object-contain hidden dark:block transition-transform duration-200 group-hover:scale-105">
                    <img src="{{ asset('assets/logo/light/icon_horizontal_light.png') }}"
                         alt="Notenux Studio"
                         class="h-6 sm:h-8 w-auto object-contain block dark:hidden transition-transform duration-200 group-hover:scale-105">
                    <span class="sr-only">NOTENUX</span>
                    <span class="text-[9px] font-mono bg-blue-900/20 text-blue-600 dark:text-cyan-300 px-1 py-0.5 rounded border border-blue-500/40 font-bold uppercase hidden sm:inline-block">STUDIO v3.0</span>
                </a>
            </div>

            <!-- Right Controls: Audio SFX, Theme Switcher, Navigation, Auth -->
            <div class="flex items-center gap-1 sm:gap-2 shrink-0">
                <!-- Sound Synthesizer SFX Button -->
                <button id="sound-toggle-btn"
                        class="arcade-btn bg-blue-100 hover:bg-blue-200 dark:bg-blue-950 dark:hover:bg-blue-900 text-blue-900 dark:text-blue-300 p-1.5 sm:px-2.5 sm:py-1.5 rounded text-xs font-mono h-8 sm:h-auto flex items-center justify-center"
                        title="Toggle 8-bit Audio Synthesizer">
                    <i class="fa-solid fa-volume-high text-blue-600 dark:text-cyan-400 sm:mr-1.5"></i> <span class="hidden sm:inline">SFX: ON</span>
                </button>

                <!-- CRT Scanlines Mode Switcher (Hidden on small mobile) -->
                <button id="crt-toggle-btn"
                        class="arcade-btn bg-purple-100 hover:bg-purple-200 dark:bg-purple-900/40 dark:hover:bg-purple-800/60 text-purple-900 dark:text-purple-300 px-2 sm:px-2.5 py-1.5 rounded text-xs font-mono hidden sm:inline-flex"
                        title="Toggle Retro CRT Scanlines Effect">
                    <i class="fa-solid fa-tv sm:mr-1.5 text-purple-600 dark:text-purple-400"></i> <span class="hidden xl:inline">CRT FX</span>
                </button>

                <!-- Arcade Shortcuts Guide Button (Desktop / Tablet) -->
                <button id="shortcuts-modal-btn"
                        class="arcade-btn bg-surface hover:bg-blue-200 dark:hover:bg-blue-900 text-main px-2 sm:px-2.5 py-1.5 rounded text-xs font-mono hidden md:inline-flex"
                        title="Arcade Controller & Keyboard Shortcuts Guide">
                    <i class="fa-solid fa-keyboard text-amber-600 dark:text-yellow-400 sm:mr-1.5"></i> <span class="hidden xl:inline">KEYS</span>
                </button>

                <!-- Light / Dark Theme Switcher -->
                <button id="theme-toggle-btn"
                        class="arcade-btn bg-amber-300 hover:bg-amber-400 dark:bg-blue-900 dark:hover:bg-blue-800 text-black dark:text-cyan-300 p-1.5 sm:px-2.5 sm:py-1.5 rounded text-xs font-mono h-8 sm:h-auto flex items-center justify-center"
                        title="Toggle Light / Dark Mode">
                    <i class="fa-solid fa-moon text-cyan-300 sm:mr-1.5"></i> <span class="hidden sm:inline">NIGHT</span>
                </button>

                <!-- Back to Landing Page -->
                <a href="{{ route('landing') }}"
                   class="arcade-btn bg-slate-200 hover:bg-slate-300 dark:bg-slate-800 dark:hover:bg-slate-700 text-main p-1.5 sm:px-2.5 sm:py-1.5 rounded text-xs font-mono h-8 sm:h-auto flex items-center justify-center"
                   title="Beranda Notenux">
                    <i class="fa-solid fa-house"></i>
                </a>

                <!-- Hero Profile & Settings Button -->
                <button type="button"
                        id="open-profile-modal-btn"
                        class="arcade-btn bg-blue-100 hover:bg-blue-200 dark:bg-blue-950 dark:hover:bg-blue-900 text-blue-950 dark:text-cyan-300 p-1.5 sm:px-2.5 sm:py-1.5 rounded text-xs font-mono h-8 sm:h-auto flex items-center justify-center border border-blue-400/50"
                        title="Profil Hero & Keamanan Akun">
                    <i class="fa-solid fa-user-shield sm:mr-1 text-blue-600 dark:text-cyan-400"></i>
                    <span class="hidden sm:inline" id="top-nav-username">{{ Str::limit($user->name ?? 'HERO', 10) }}</span>
                </button>

                <!-- Logout Button -->
                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button type="submit"
                            id="logout-btn"
                            class="arcade-btn bg-red-600 hover:bg-red-500 text-white p-1.5 sm:px-2.5 sm:py-1.5 rounded text-xs font-mono h-8 sm:h-auto flex items-center justify-center"
                            title="Keluar dari Studio">
                        <i class="fa-solid fa-right-from-bracket sm:mr-1"></i> <span class="hidden sm:inline">LOGOUT</span>
                    </button>
                </form>

                <!-- Hidden inspect chunks button replaced with functional Shards button -->
                <button type="button"
                        id="inspect-chunks-btn"
                        class="arcade-btn bg-slate-200 hover:bg-slate-300 dark:bg-slate-800 dark:hover:bg-slate-700 text-main p-1.5 sm:px-2.5 sm:py-1.5 rounded text-xs font-mono h-8 sm:h-auto flex items-center justify-center border border-black/20 dark:border-blue-900"
                        title="Database Shard Telemetry & Integrity Audit">
                    <i class="fa-solid fa-cubes text-cyan-600 dark:text-cyan-400 sm:mr-1"></i>
                    <span class="hidden sm:inline">CHUNKS</span>
                </button>
            </div>
        </div>
    </header>

    <!-- Main Multi-Sidebar Workspace Container -->
    <div class="flex-1 flex overflow-hidden relative min-h-0">

        <!-- Mobile Backdrop Overlay -->
        <div id="sidebar-overlay"
             class="fixed inset-0 bg-black/60 backdrop-blur-xs z-35 hidden lg:hidden transition-opacity"></div>

        <!-- ============================================================================== -->
        <!-- SIDEBAR 1: PRIMARY NAVIGATION & PLAYER PROFILE SIDEBAR -->
        <!-- ============================================================================== -->
        <aside id="primary-nav-sidebar"
               class="fixed inset-y-0 left-0 z-40 lg:static lg:z-auto w-64 max-w-[85vw] shrink-0 h-full bg-[var(--bg-surface)] border-r-2 border-black dark:border-blue-900 flex flex-col transition-all duration-200 ease-in-out -translate-x-full lg:translate-x-0 overflow-hidden">

            <div class="p-3 border-b-2 border-black/10 dark:border-white/10 flex items-center justify-between lg:hidden">
                <div class="flex items-center gap-2">
                    <img src="{{ asset('assets/logo/dark/icon_dark.png') }}" alt="Notenux Icon" class="w-5 h-5 object-contain hidden dark:inline-block">
                    <img src="{{ asset('assets/logo/light/icon_light.png') }}" alt="Notenux Icon" class="w-5 h-5 object-contain inline-block dark:hidden">
                    <span class="font-pixel text-[11px] text-blue-500">
                        NAVIGATION
                    </span>
                </div>
                <button id="close-mobile-nav-btn" class="arcade-btn bg-red-600 text-white text-xs px-2 py-1 rounded">
                    <i class="fa-solid fa-xmark mr-1"></i> CLOSE
                </button>
            </div>

            <!-- Player Gamification Status Card -->
            <div class="player-profile-section p-3 border-b-2 border-black/10 dark:border-white/10">
                <div class="arcade-card player-status-card p-2.5 rounded-lg border-2 border-blue-500 bg-blue-50 dark:bg-blue-950/40 shadow-[2px_2px_0px_#000]">
                    <div class="player-avatar-row flex items-center gap-2.5 mb-2">
                        <!-- Pixel Wizard Avatar with FA icon -->
                        <div class="player-avatar-box w-9 h-9 rounded border-2 border-black bg-blue-600 flex items-center justify-center text-white shrink-0 shadow-[2px_2px_0px_#000] relative cursor-pointer hover:bg-blue-500 transition-colors"
                             id="sidebar-avatar-profile-btn"
                             title="Kelola Profil & Keamanan ({{ $user->name ?? 'Hero' }})">
                            <i class="fa-solid fa-hat-wizard text-sm"></i>
                            <span class="collapsed-level-badge hidden font-pixel text-[8px] bg-amber-400 text-black px-1 rounded border border-black absolute -bottom-1 -right-1 shadow-[1px_1px_0px_#000]">
                                LV.{{ $stats['level'] ?? 1 }}
                            </span>
                        </div>
                        <div class="min-w-0 flex-1 nav-text-label">
                            <div class="flex items-center justify-between gap-1">
                                <h3 id="player-rank-title" class="font-bold text-xs truncate text-blue-950 dark:text-cyan-300">
                                    <i class="fa-solid fa-award text-amber-600 dark:text-yellow-400 mr-1"></i> {{ $stats['rank_title'] ?? 'Novice Scribe' }}
                                </h3>
                                <span id="player-level-badge" class="font-pixel text-[9px] bg-blue-600 text-white px-1.5 py-0.5 rounded border border-black shrink-0">
                                    LV.{{ $stats['level'] ?? 1 }}
                                </span>
                            </div>
                            <div class="text-[10px] font-mono text-slate-700 dark:text-gray-300 flex items-center justify-between mt-0.5">
                                <span id="player-mana-text" class="font-bold"><i class="fa-solid fa-wand-magic mr-1 text-blue-600 dark:text-cyan-400"></i>{{ $stats['mana'] ?? '50/100 MP' }}</span>
                                <span id="player-xp-text" class="text-amber-600 dark:text-amber-400 font-bold">{{ $stats['total_xp'] ?? 10 }} XP</span>
                            </div>
                        </div>
                    </div>

                    <!-- XP Progress Bar -->
                    <div class="w-full bg-black/20 dark:bg-black/60 h-2 rounded border border-black/40 overflow-hidden p-[1px] nav-hide-on-collapse">
                        <div id="player-xp-bar"
                             class="h-full bg-gradient-to-r from-blue-500 to-cyan-400 rounded transition-all duration-300"
                             style="width: {{ $stats['progress_percent'] ?? 15 }}%;"></div>
                    </div>
                </div>
            </div>

            <!-- Primary Nav Links -->
            <nav class="primary-nav-links flex-1 overflow-y-auto p-3 space-y-1.5 font-mono text-xs">
                <div class="flex items-center justify-between mb-1 px-2 nav-text-label">
                    <span class="text-[10px] font-bold text-slate-600 dark:text-gray-400 uppercase tracking-wider">Grimoire Categories</span>
                    <a href="{{ route('categories.index') }}"
                       class="arcade-btn bg-surface hover:bg-cyan-200 dark:hover:bg-blue-900 px-1.5 py-0.5 rounded text-[9px] font-bold text-cyan-600 dark:text-cyan-400"
                       title="Personalize and manage categories">
                        <i class="fa-solid fa-plus text-[8px] mr-0.5"></i> CUSTOM
                    </a>
                </div>

                <div class="collapsed-cat-header hidden my-1 justify-center">
                    <a href="{{ route('categories.index') }}"
                       class="arcade-btn w-9 h-9 rounded-lg bg-surface hover:bg-cyan-200 dark:hover:bg-blue-900 border border-black/30 dark:border-blue-700 flex items-center justify-center text-cyan-600 dark:text-cyan-400 text-xs shadow-[1px_1px_0px_#000]"
                       title="Personalize & Forge Categories">
                        <i class="fa-solid fa-folder-plus text-xs"></i>
                    </a>
                </div>

                <a href="#" class="nav-category-link flex items-center gap-2.5 px-3 py-2 rounded-lg border border-transparent hover:border-black/30 dark:hover:border-cyan-400/40 hover:bg-blue-50 dark:hover:bg-blue-900/30 transition-colors bg-blue-600 text-white"
                   data-category="all"
                   title="All Scrolls">
                    <i class="fa-solid fa-book-journal-whills text-sm w-4 text-center"></i>
                    <span class="nav-text-label font-bold">All Scrolls</span>
                </a>

                <div id="sidebar-categories-list" class="space-y-1">
                    @if(isset($categories) && $categories->isNotEmpty())
                        @foreach($categories as $category)
                            <div class="nav-category-item group flex items-center justify-between rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900/30 transition-colors pr-1.5"
                                 data-category="{{ $category->slug }}">
                                <a href="#" class="nav-category-link flex-1 flex items-center gap-2.5 px-3 py-2 rounded-lg truncate"
                                   data-category="{{ $category->slug }}"
                                   title="{{ $category->name }}">
                                    <i class="{{ $category->icon }} text-sm w-4 text-center shrink-0"></i>
                                    <span class="nav-text-label font-bold truncate">{{ $category->name }}</span>
                                </a>
                                <div class="hidden group-hover:flex items-center gap-1 shrink-0">
                                    <button type="button"
                                            class="sidebar-edit-category-btn text-[10px] text-cyan-600 dark:text-cyan-400 hover:scale-110 transition-transform p-1"
                                            data-slug="{{ $category->slug }}"
                                            title="Edit Category">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <button type="button"
                                            class="sidebar-delete-category-btn text-[10px] text-rose-500 hover:scale-110 transition-transform p-1"
                                            data-slug="{{ $category->slug }}"
                                            title="Delete Category">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <div class="nav-category-item group flex items-center justify-between rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900/30 transition-colors pr-1.5"
                             data-category="catatan">
                            <a href="#" class="nav-category-link flex-1 flex items-center gap-2.5 px-3 py-2 rounded-lg truncate"
                               data-category="catatan"
                               title="Catatan">
                                <i class="fa-solid fa-note-sticky text-blue-500 text-sm w-4 text-center shrink-0"></i>
                                <span class="nav-text-label font-bold">Catatan</span>
                            </a>
                        </div>
                        <div class="nav-category-item group flex items-center justify-between rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900/30 transition-colors pr-1.5"
                             data-category="tugas">
                            <a href="#" class="nav-category-link flex-1 flex items-center gap-2.5 px-3 py-2 rounded-lg truncate"
                               data-category="tugas"
                               title="Tugas">
                                <i class="fa-solid fa-list-check text-emerald-500 text-sm w-4 text-center shrink-0"></i>
                                <span class="nav-text-label font-bold">Tugas</span>
                            </a>
                        </div>
                    @endif
                </div>
            </nav>

            <!-- Bottom Primary Nav Meta -->
            <div class="p-3 border-t-2 border-black/10 dark:border-white/10 text-[10px] font-mono opacity-70 flex items-center justify-between nav-text-label">
                <span><i class="fa-solid fa-server mr-1"></i> Cloud Database</span>
                <span><i class="fa-solid fa-cloud-arrow-up mr-1"></i> Auto-Sync</span>
            </div>
        </aside>

        <!-- ============================================================================== -->
        <!-- SIDEBAR 2: SCROLL / NOTES EXPLORER SIDEBAR (Dedicated Inner Sidebar) -->
        <!-- ============================================================================== -->
        <aside id="notes-explorer-sidebar"
               class="fixed inset-y-0 left-0 lg:left-auto z-40 lg:static lg:z-auto w-80 max-w-[88vw] sm:w-80 shrink-0 h-full bg-[var(--bg-surface-alt)] border-r-2 border-black dark:border-blue-900 flex flex-col transition-all duration-200 ease-in-out -translate-x-full lg:translate-x-0 overflow-hidden">

            <div class="p-3.5 flex-1 flex flex-col overflow-hidden">
                <!-- Explorer Header -->
                <div class="flex items-center justify-between pb-2.5 mb-2.5 border-b-2 border-dashed border-blue-500/30">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-folder-open text-blue-600 dark:text-cyan-400"></i>
                        <span class="font-pixel text-[11px] text-blue-900 dark:text-cyan-300">SCROLL DIRECTORY</span>
                    </div>

                    <!-- Directory Close Button -->
                    <button id="close-mobile-explorer-btn" class="arcade-btn bg-red-600 hover:bg-red-500 text-white text-xs px-2 py-1 rounded" title="Close Scroll Directory">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <!-- New Note Action & Import Button Row -->
                <div class="flex items-center gap-2 mb-3">
                    <button id="new-note-btn"
                            class="arcade-btn flex-1 bg-blue-600 hover:bg-blue-500 text-white py-2 px-2.5 rounded text-xs font-mono tracking-wider">
                        <i class="fa-solid fa-plus mr-1.5"></i> NEW SCROLL
                    </button>
                    <button id="import-note-btn"
                            class="arcade-btn bg-emerald-600 hover:bg-emerald-500 text-white py-2 px-2.5 rounded text-xs font-mono tracking-wider"
                            title="Import Markdown (.md) or Text file into a new Scroll">
                        <i class="fa-solid fa-file-import mr-1"></i> IMPORT
                    </button>
                </div>

                <!-- Search Input with Font Awesome Icon -->
                <div class="relative mb-2">
                    <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-slate-500 dark:text-gray-400 text-xs">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </span>
                    <input type="text"
                           id="search-notes-input"
                           placeholder="Search scrolls, tags, lore..."
                           class="w-full bg-white dark:bg-black/40 border-2 border-black dark:border-blue-700 rounded pl-8 pr-2.5 py-1.5 text-xs font-mono focus:outline-none focus:border-cyan-400 shadow-[2px_2px_0px_#000]">
                </div>

                <!-- Sort Notes & Multi-Select Row -->
                <div class="flex items-center justify-between gap-1 mb-2 px-1 text-[11px] font-mono">
                    <div class="flex items-center gap-1 min-w-0">
                        <span class="opacity-75 shrink-0"><i class="fa-solid fa-arrow-down-wide-short mr-1"></i>SORT:</span>
                        <select id="sort-notes-select" class="bg-white dark:bg-black/50 border border-black/40 rounded px-1.5 py-0.5 text-[10px] font-mono focus:outline-none focus:border-cyan-400 truncate">
                            <option value="updated_desc">Recently Updated</option>
                            <option value="updated_asc">Oldest First</option>
                            <option value="created_desc">Newest Inscribed</option>
                            <option value="created_asc">Oldest Inscribed</option>
                            <option value="title_asc">Title A-Z</option>
                            <option value="title_desc">Title Z-A</option>
                            <option value="word_count_desc">Word Count</option>
                            <option value="level_desc">Level / XP</option>
                        </select>
                    </div>
                    <button id="toggle-bulk-select-notes-btn"
                            type="button"
                            class="arcade-btn bg-slate-200 dark:bg-slate-800 hover:bg-blue-100 dark:hover:bg-blue-900 text-main text-[10px] font-mono px-2 py-0.5 rounded border border-black/30 dark:border-blue-700 flex items-center gap-1 shrink-0"
                            title="Aktifkan mode multiple choice / pilih banyak gulungan">
                        <i class="fa-solid fa-list-check text-blue-600 dark:text-cyan-400"></i>
                        <span id="bulk-toggle-label">PILIH</span>
                    </button>
                </div>

                <!-- Sticky / Collapsible Bulk Actions Bar for Scrolls -->
                <div id="notes-bulk-bar" class="hidden mb-2 p-2 bg-blue-50 dark:bg-blue-950/70 border-2 border-blue-600 dark:border-cyan-400 rounded-lg shadow-[2px_2px_0px_#000] font-mono text-[11px]">
                    <div class="flex items-center justify-between gap-1 mb-1.5">
                        <label class="flex items-center gap-1.5 cursor-pointer font-bold select-none text-[10px]">
                            <input type="checkbox" id="bulk-select-all-notes-cb" class="cursor-pointer accent-blue-600 w-3.5 h-3.5">
                            <span>PILIH SEMUA</span>
                        </label>
                        <span id="bulk-selected-notes-count" class="font-bold text-blue-700 dark:text-cyan-300 bg-white dark:bg-black/50 px-1.5 py-0.5 rounded border border-blue-400/40 text-[10px]">
                            0 dipilih
                        </span>
                    </div>
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <button id="bulk-pin-notes-btn"
                                type="button"
                                disabled
                                class="arcade-btn bg-amber-500 hover:bg-amber-400 disabled:opacity-50 disabled:cursor-not-allowed text-white py-1 px-2 rounded text-[10px] font-bold flex items-center justify-center gap-1"
                                title="Sematkan / Lepas Pin semua terpilih">
                            <i class="fa-solid fa-thumbtack"></i> PIN
                        </button>
                        <button id="bulk-fav-notes-btn"
                                type="button"
                                disabled
                                class="arcade-btn bg-yellow-500 hover:bg-yellow-400 disabled:opacity-50 disabled:cursor-not-allowed text-black py-1 px-2 rounded text-[10px] font-bold flex items-center justify-center gap-1"
                                title="Favoritkan / Batal Favorit semua terpilih">
                            <i class="fa-solid fa-star"></i> FAV
                        </button>
                        <select id="bulk-move-category-select"
                                disabled
                                class="arcade-btn bg-surface hover:bg-blue-100 dark:hover:bg-blue-900 disabled:opacity-50 disabled:cursor-not-allowed text-main py-1 px-1.5 rounded text-[10px] font-mono font-bold border border-black/40 shadow-[1px_1px_0px_#000] cursor-pointer"
                                title="Pindahkan gulungan terpilih ke kategori lain">
                            <option value="">PINDAH KATEGORI...</option>
                            @if(isset($categories) && $categories->isNotEmpty())
                                @foreach($categories as $category)
                                    <option value="{{ $category->slug }}">{{ $category->name }}</option>
                                @endforeach
                            @endif
                        </select>
                        <button id="bulk-delete-notes-btn"
                                type="button"
                                disabled
                                class="arcade-btn flex-1 bg-rose-600 hover:bg-rose-500 disabled:opacity-50 disabled:cursor-not-allowed text-white py-1 px-2 rounded text-[10px] font-bold tracking-wider flex items-center justify-center gap-1">
                            <i class="fa-solid fa-trash-can"></i> HAPUS
                        </button>
                        <button id="bulk-cancel-notes-btn"
                                type="button"
                                class="arcade-btn bg-surface hover:bg-slate-200 dark:hover:bg-slate-800 text-main py-1 px-2 rounded text-[10px] font-bold">
                            BATAL
                        </button>
                    </div>
                </div>

                <!-- Category Filter Pills -->
                <div class="flex items-center gap-1 overflow-x-auto pb-2 mb-2 text-[11px] font-mono scrollbar-none" id="category-filter-pills-row">
                    <button class="category-filter-btn px-2 py-0.5 rounded border border-black/40 bg-blue-600 text-white active-category whitespace-nowrap shadow-[1px_1px_0px_#000]"
                            data-category="all">
                        ALL
                    </button>
                    <button class="category-filter-btn px-2 py-0.5 rounded border border-black/40 bg-surface whitespace-nowrap hover:bg-amber-100 dark:hover:bg-amber-950/60 text-amber-700 dark:text-amber-400 shadow-[1px_1px_0px_#000]"
                            data-category="pinned"
                            title="Filter hanya catatan yang disematkan (Pinned)">
                        <i class="fa-solid fa-thumbtack mr-1"></i>PINNED
                    </button>
                    <button class="category-filter-btn px-2 py-0.5 rounded border border-black/40 bg-surface whitespace-nowrap hover:bg-yellow-100 dark:hover:bg-yellow-950/60 text-yellow-700 dark:text-yellow-400 shadow-[1px_1px_0px_#000]"
                            data-category="favorites"
                            title="Filter hanya catatan favorit (Favorites)">
                        <i class="fa-solid fa-star mr-1"></i>FAV
                    </button>
                    @if(isset($categories) && $categories->isNotEmpty())
                        @foreach($categories as $category)
                            <button class="category-filter-btn px-2 py-0.5 rounded border border-black/40 bg-surface whitespace-nowrap hover:bg-blue-200 dark:hover:bg-blue-900 shadow-[1px_1px_0px_#000]"
                                    data-category="{{ $category->slug }}">
                                <i class="{{ $category->icon }} mr-1"></i>{{ strtoupper(Str::limit($category->name, 10, '')) }}
                            </button>
                        @endforeach
                    @else
                        <button class="category-filter-btn px-2 py-0.5 rounded border border-black/40 bg-surface whitespace-nowrap hover:bg-blue-200 dark:hover:bg-blue-900 shadow-[1px_1px_0px_#000]"
                                data-category="catatan">
                            <i class="fa-solid fa-note-sticky text-blue-500 mr-1"></i>CATATAN
                        </button>
                        <button class="category-filter-btn px-2 py-0.5 rounded border border-black/40 bg-surface whitespace-nowrap hover:bg-blue-200 dark:hover:bg-blue-900 shadow-[1px_1px_0px_#000]"
                                data-category="tugas">
                            <i class="fa-solid fa-list-check text-emerald-500 mr-1"></i>TUGAS
                        </button>
                    @endif
                </div>

                <!-- Dynamic Notes List Container -->
                <div class="flex-1 overflow-y-auto pr-1 space-y-1" id="notes-list">
                    <!-- Populated dynamically via app.js -->
                </div>
            </div>
        </aside>

        <!-- ============================================================================== -->
        <!-- MAIN PANEL & EDITABLE WORKSPACE WITH ZOOM & MARKDOWN SUPPORT -->
        <!-- ============================================================================== -->
        <main class="flex-1 flex flex-col overflow-y-auto overflow-x-hidden p-2 sm:p-4 lg:p-6 bg-[var(--bg-main)] min-w-0 max-w-full">
            <div class="w-full mx-auto flex-1 flex flex-col space-y-2.5 sm:space-y-3 min-w-0 max-w-full h-full">

                <!-- ============================================================================== -->
                <!-- DYNAMIC ISLAND: FLOATING ADAPTIVE RETRO-MODERN HUD & COMMAND CAPSULE -->
                <!-- ============================================================================== -->
                <div id="studio-dynamic-island" class="dynamic-island-container sticky top-0 z-30 w-full transition-all duration-300">
                    <!-- Island Capsule Pill (Compact Header / Always Visible & Interactive) -->
                    <div class="dynamic-island-capsule arcade-card p-2 sm:p-2.5 rounded-2xl flex flex-col gap-2 shadow-[4px_4px_0px_#000] backdrop-blur-md bg-[var(--bg-surface)]/95 border-2 border-black dark:border-cyan-500/60 min-w-0 max-w-full">
                        <!-- Capsule Main Row: Quick Identity, Live Telemetry & Island Controls -->
                        <div class="flex items-center justify-between gap-2 w-full min-w-0">
                            <!-- Left: Scroll Icon & Unconstrained Title & Category Pill -->
                            <div class="flex items-center gap-1.5 sm:gap-2.5 min-w-0 flex-1">
                                <!-- Retro Scroll Pill Icon -->
                                <div class="w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-950/80 border-2 border-black dark:border-cyan-500 flex items-center justify-center shrink-0 shadow-[2px_2px_0px_#000]">
                                    <i class="fa-solid fa-scroll text-sm text-blue-600 dark:text-cyan-400"></i>
                                </div>

                                <!-- Scroll Title (Full Width, Unconstrained & Interactive) -->
                                <input type="text"
                                       id="note-title-input"
                                       placeholder="{{ $activeNote ? 'Judul catatan...' : 'Belum ada catatan dipilih...' }}"
                                       value="{{ $activeNote->title ?? '' }}"
                                       {{ $activeNote ? '' : 'readonly' }}
                                       class="min-w-0 flex-1 text-sm sm:text-lg font-bold bg-transparent border-b-2 border-dashed border-black/15 dark:border-blue-800 hover:border-blue-400 focus:border-cyan-400 focus:outline-none transition-colors px-1 py-0.5 truncate"
                                       title="Scroll Title">

                                <!-- Compact Category Pill Dropdown -->
                                <div class="hidden md:flex items-center gap-1 bg-surface-alt px-2 py-1 rounded-lg border border-black/20 dark:border-blue-900 shadow-[1px_1px_0px_#000] shrink-0">
                                    <i class="fa-solid fa-tag text-blue-500 dark:text-cyan-400 text-xs shrink-0"></i>
                                    <select id="note-category-select" class="bg-transparent border-0 text-xs font-mono font-bold focus:outline-none cursor-pointer max-w-[140px] truncate">
                                        @if(isset($categories) && $categories->isNotEmpty())
                                            @foreach($categories as $category)
                                                <option value="{{ $category->slug }}" data-id="{{ $category->id }}">[{{ strtoupper($category->slug) }}] {{ $category->name }}</option>
                                            @endforeach
                                        @else
                                            <option value="catatan">[CATATAN] Catatan</option>
                                            <option value="tugas">[TUGAS] Tugas</option>
                                        @endif
                                    </select>
                                </div>
                            </div>

                            <!-- Center/Right: Live Telemetry HUD Badges -->
                            <div class="hidden xl:flex items-center gap-1.5 font-mono text-xs shrink-0">
                                <!-- Word & Reading Badges -->
                                <span id="word-count-badge" class="px-2 py-0.5 rounded border border-black/20 dark:border-blue-900 bg-blue-100 dark:bg-blue-900/40 text-blue-950 dark:text-blue-100 text-[11px] font-bold flex items-center shrink-0">
                                    <i class="fa-solid fa-pen-nib mr-1 text-blue-600 dark:text-blue-400"></i> 0 Words
                                </span>
                                <span id="char-count-badge" class="px-2 py-0.5 rounded border border-black/20 dark:border-blue-900 bg-blue-100 dark:bg-blue-900/40 text-blue-950 dark:text-blue-100 text-[11px] font-bold hidden 2xl:inline-flex items-center shrink-0">
                                    <i class="fa-solid fa-font mr-1 text-blue-600 dark:text-blue-400"></i> 0 Chars
                                </span>
                                <span id="reading-time-badge" class="px-2 py-0.5 rounded border border-black/20 dark:border-blue-900 bg-blue-100 dark:bg-blue-900/40 text-blue-950 dark:text-blue-100 text-[11px] font-bold flex items-center shrink-0">
                                    <i class="fa-solid fa-clock mr-1 text-blue-600 dark:text-cyan-400"></i> 1m Read
                                </span>

                                <!-- Created & Updated Badges -->
                                <span id="scroll-created-badge"
                                      class="px-1.5 py-0.5 rounded border border-black/20 dark:border-blue-900 bg-surface-alt text-slate-700 dark:text-slate-300 font-mono text-[10px] hidden 2xl:inline-flex items-center gap-1 shadow-[1px_1px_0px_#000] shrink-0"
                                      title="Waktu Gulungan Ini Dibuat">
                                    <i class="fa-solid fa-calendar-plus text-blue-600 dark:text-cyan-400"></i>
                                    <span id="scroll-created-text">Dibuat: -</span>
                                </span>
                                <span id="scroll-updated-badge"
                                      class="px-1.5 py-0.5 rounded border border-black/20 dark:border-blue-900 bg-surface-alt text-slate-700 dark:text-slate-300 font-mono text-[10px] inline-flex items-center gap-1 shadow-[1px_1px_0px_#000] shrink-0"
                                      title="Waktu Gulungan Ini Terakhir Diperbarui">
                                    <i class="fa-solid fa-clock-rotate-left text-emerald-600 dark:text-emerald-400 shrink-0"></i>
                                    <span id="scroll-updated-text" class="truncate max-w-[130px]">Diperbarui: -</span>
                                </span>

                                <!-- Quest Goal Tracker Pill -->
                                <div class="flex items-center gap-1 bg-surface-alt px-1.5 py-0.5 rounded border border-black/20 dark:border-blue-900 text-[11px] font-mono shadow-[1px_1px_0px_#000] shrink-0">
                                    <i class="fa-solid fa-bullseye text-amber-600 dark:text-amber-400"></i>
                                    <span id="quest-goal-text" class="font-bold text-slate-900 dark:text-slate-100">Goal: 0/250w</span>
                                    <select id="quest-goal-select" class="no-retro-select bg-transparent border-0 text-[10px] focus:outline-none ml-0.5 cursor-pointer font-bold text-slate-900 dark:text-slate-100 opacity-90 hover:opacity-100">
                                        <option value="100">100w</option>
                                        <option value="250" selected>250w</option>
                                        <option value="500">500w</option>
                                        <option value="1000">1000w</option>
                                    </select>
                                </div>

                                <!-- Save Status Indicator -->
                                <span id="save-status-text" class="text-[11px] font-mono text-emerald-700 dark:text-emerald-400 flex items-center gap-1 font-bold shrink-0">
                                    <i class="fa-solid fa-circle-check"></i> Synchronized
                                </span>
                                <button id="chunk-count-badge"
                                        type="button"
                                        class="arcade-btn bg-surface hover:bg-cyan-100 dark:hover:bg-cyan-950/60 text-cyan-800 dark:text-cyan-300 px-2 py-0.5 rounded text-[10px] font-mono font-bold border border-black/20 dark:border-cyan-800 shadow-[1px_1px_0px_#000] cursor-pointer flex items-center gap-1 shrink-0"
                                        title="Audit Database Chunks & Shards Telemetry">
                                    <i class="fa-solid fa-cubes text-cyan-600 dark:text-cyan-400"></i>
                                    <span>0 Chunks</span>
                                </button>
                            </div>

                            <!-- Right: Quick Essential Actions + Dynamic Island Toggle Button -->
                            <div class="flex items-center gap-1 sm:gap-1.5 shrink-0">
                                <!-- Favorite Bookmark -->
                                <button id="favorite-note-btn"
                                        class="arcade-btn bg-surface hover:bg-amber-100 dark:hover:bg-amber-900/40 w-7 h-7 sm:w-8 sm:h-8 rounded-lg text-xs flex items-center justify-center shrink-0"
                                        title="Toggle Favorite Bookmark">
                                    <i class="fa-regular fa-star text-amber-600 dark:text-yellow-400"></i>
                                </button>

                                <!-- Pin Button -->
                                <button id="pin-note-btn"
                                        class="arcade-btn bg-blue-600 hover:bg-blue-500 text-white w-7 h-7 sm:w-8 sm:h-8 rounded-lg text-xs font-mono flex items-center justify-center shrink-0"
                                        title="Toggle Pinned Note">
                                    <i class="fa-solid fa-thumbtack"></i>
                                </button>

                                <!-- Save Now Button -->
                                <button id="save-now-btn"
                                        class="arcade-btn bg-emerald-600 hover:bg-emerald-500 text-white px-2.5 sm:px-3 h-7 sm:h-8 rounded-lg text-xs font-mono font-bold tracking-wider flex items-center justify-center shrink-0"
                                        title="Simpan naskah catatan sekarang">
                                    <i class="fa-solid fa-floppy-disk mr-1"></i> SAVE
                                </button>

                                <!-- Dynamic Island Slide Toggle Button -->
                                <button type="button"
                                        id="dynamic-island-toggle-btn"
                                        class="arcade-btn bg-blue-700 hover:bg-blue-600 text-white px-2.5 sm:px-3.5 h-7 sm:h-8 rounded-lg text-xs font-mono font-bold flex items-center gap-1.5 shadow-[2px_2px_0px_#000] cursor-pointer shrink-0 transition-colors"
                                        title="Buka / Tutup Dynamic Island Tools (Alt+T)"
                                        aria-expanded="false"
                                        aria-controls="dynamic-island-tray">
                                    <i class="fa-solid fa-layer-group text-cyan-300"></i>
                                    <span class="hidden sm:inline font-pixel text-[10px]">TOOLS</span>
                                    <i id="island-chevron-icon" class="fa-solid fa-chevron-down text-[10px] transition-transform duration-300"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Dynamic Island Sliding Tray (Animasi slide-down fluid & horizontal scrollable rails) -->
                    <div id="dynamic-island-tray"
                         class="collapsed dynamic-island-slide-tray arcade-card mt-2 p-2.5 sm:p-3 rounded-2xl flex flex-col gap-2.5 shadow-[4px_4px_0px_#000] backdrop-blur-lg bg-[var(--bg-surface)]/98 border-2 border-black dark:border-cyan-500/80 min-w-0 max-w-full">

                        <!-- Interactive Category Chips ("munculnya digulir, dan itu interaktif") -->
                        <div class="flex items-center justify-between gap-2 pb-2 border-b border-black/10 dark:border-white/10 w-full min-w-0">
                            <!-- Category Chips Carousel / Track -->
                            <div id="island-chips-track" class="flex items-center gap-1.5 overflow-x-auto no-scrollbar scroll-smooth touch-pan-x flex-1 min-w-0 py-0.5">
                                <button type="button" data-island-section="all" class="island-chip-btn active px-2.5 py-1 rounded-lg text-xs font-mono font-bold border border-black/20 dark:border-cyan-500/40 bg-blue-600 text-white shadow-[1px_1px_0px_#000] shrink-0 transition-all flex items-center gap-1.5">
                                    <i class="fa-solid fa-border-all text-[11px]"></i>
                                    <span>Semua Tools</span>
                                </button>
                                <button type="button" data-island-section="formatting" class="island-chip-btn px-2.5 py-1 rounded-lg text-xs font-mono font-bold border border-black/20 dark:border-blue-900 bg-surface hover:bg-blue-100 dark:hover:bg-blue-900/40 text-slate-700 dark:text-slate-300 shadow-[1px_1px_0px_#000] shrink-0 transition-all flex items-center gap-1.5">
                                    <i class="fa-solid fa-font text-blue-600 dark:text-cyan-400 text-[11px]"></i>
                                    <span>✍️ Teks & Format</span>
                                </button>
                                <button type="button" data-island-section="inserts" class="island-chip-btn px-2.5 py-1 rounded-lg text-xs font-mono font-bold border border-black/20 dark:border-blue-900 bg-surface hover:bg-blue-100 dark:hover:bg-blue-900/40 text-slate-700 dark:text-slate-300 shadow-[1px_1px_0px_#000] shrink-0 transition-all flex items-center gap-1.5">
                                    <i class="fa-solid fa-table-cells text-emerald-600 dark:text-emerald-400 text-[11px]"></i>
                                    <span>🧱 Tabel & Sisipan</span>
                                </button>
                                <button type="button" data-island-section="studio" class="island-chip-btn px-2.5 py-1 rounded-lg text-xs font-mono font-bold border border-black/20 dark:border-blue-900 bg-surface hover:bg-blue-100 dark:hover:bg-blue-900/40 text-slate-700 dark:text-slate-300 shadow-[1px_1px_0px_#000] shrink-0 transition-all flex items-center gap-1.5">
                                    <i class="fa-solid fa-wand-magic-sparkles text-purple-600 dark:text-purple-400 text-[11px]"></i>
                                    <span>⚡ Mode & Studio</span>
                                </button>
                                <button type="button" data-island-section="operations" class="island-chip-btn px-2.5 py-1 rounded-lg text-xs font-mono font-bold border border-black/20 dark:border-blue-900 bg-surface hover:bg-blue-100 dark:hover:bg-blue-900/40 text-slate-700 dark:text-slate-300 shadow-[1px_1px_0px_#000] shrink-0 transition-all flex items-center gap-1.5">
                                    <i class="fa-solid fa-box-archive text-amber-600 dark:text-amber-400 text-[11px]"></i>
                                    <span>📊 Arsip & Operasi</span>
                                </button>
                            </div>

                            <!-- Scroll Arrows & Close Island Button -->
                            <div class="flex items-center gap-1 shrink-0">
                                <button type="button" id="island-scroll-left-btn" class="arcade-btn bg-surface hover:bg-blue-100 dark:hover:bg-blue-900/60 w-6 h-6 rounded flex items-center justify-center text-[10px]" title="Scroll Tools Left">
                                    <i class="fa-solid fa-chevron-left"></i>
                                </button>
                                <button type="button" id="island-scroll-right-btn" class="arcade-btn bg-surface hover:bg-blue-100 dark:hover:bg-blue-900/60 w-6 h-6 rounded flex items-center justify-center text-[10px]" title="Scroll Tools Right">
                                    <i class="fa-solid fa-chevron-right"></i>
                                </button>
                                <button type="button" id="island-close-btn" class="arcade-btn bg-surface hover:bg-rose-100 dark:hover:bg-rose-950/60 text-rose-500 w-6 h-6 rounded flex items-center justify-center text-[10px] ml-1" title="Tutup Dynamic Island (Esc / Alt+T)">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </div>
                        </div>

                        <!-- The Horizontally Scrollable Tool Decks -->
                        <div id="editor-toolbar-decks" class="flex flex-col gap-2 min-w-0 max-w-full">
                            <!-- Toolbar Deck 1: Typography & Inline Formatting -->
                            <div id="editor-deck-1" class="island-tool-deck flex items-center gap-1 sm:gap-1.5 pb-2 border-b border-black/10 dark:border-white/10 overflow-x-auto no-scrollbar scroll-smooth touch-pan-x flex-nowrap sm:flex-wrap w-full min-w-0">
                                <div class="flex items-center gap-1 sm:gap-1.5 shrink-0 sm:shrink sm:flex-wrap">
                                    <!-- Font Family & Size Combined Pill -->
                                    <div class="flex items-center gap-1 bg-surface-alt p-1 rounded-lg border border-black/20 dark:border-blue-900 shadow-[1px_1px_0px_#000] shrink-0">
                                        <i class="fa-solid fa-font text-blue-500 dark:text-cyan-400 text-xs px-0.5 shrink-0"></i>
                                        <select id="font-family-select"
                                                class="bg-white dark:bg-black/50 border border-black/30 dark:border-blue-800 rounded px-1.5 py-0.5 text-xs font-mono focus:outline-none focus:border-cyan-400 max-w-[125px] sm:max-w-[145px]">
                                            <option value="pixel">Pixel (Press Start 2P)</option>
                                            <option value="arcade">Arcade (VT323)</option>
                                            <option value="mono">Cyber Mono (Fira Code)</option>
                                            <option value="sans">Modern Sans (Instrument)</option>
                                            <option value="serif">Grimoire (Merriweather)</option>
                                        </select>
                                        <span class="text-black/30 dark:text-white/30 text-xs">|</span>
                                        <select id="font-size-select"
                                                class="bg-white dark:bg-black/50 border border-black/30 dark:border-blue-800 rounded px-1 py-0.5 text-xs font-mono focus:outline-none focus:border-cyan-400 w-16">
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

                                    <!-- Undo & Redo -->
                                    <div class="flex items-center gap-1 bg-surface-alt p-1 rounded-lg border border-black/20 dark:border-blue-900 shadow-[1px_1px_0px_#000] shrink-0">
                                        <button id="editor-undo-btn" data-command="undo" class="arcade-btn bg-surface hover:bg-blue-200 dark:hover:bg-blue-800 w-7 h-7 rounded text-xs flex items-center justify-center shrink-0" title="Undo (Ctrl+Z)">
                                            <i class="fa-solid fa-rotate-left"></i>
                                        </button>
                                        <button id="editor-redo-btn" data-command="redo" class="arcade-btn bg-surface hover:bg-blue-200 dark:hover:bg-blue-800 w-7 h-7 rounded text-xs flex items-center justify-center shrink-0" title="Redo (Ctrl+Y / Ctrl+Shift+Z)">
                                            <i class="fa-solid fa-rotate-right"></i>
                                        </button>
                                    </div>

                                    <!-- Bold, Italic, Underline, Strikethrough, Link, Unlink -->
                                    <div class="flex items-center gap-1 bg-surface-alt p-1 rounded-lg border border-black/20 dark:border-blue-900 shadow-[1px_1px_0px_#000] shrink-0">
                                        <button data-command="bold" class="arcade-btn bg-surface hover:bg-blue-200 dark:hover:bg-blue-800 w-7 h-7 rounded text-xs font-bold flex items-center justify-center shrink-0" title="Bold (Ctrl+B)">
                                            <i class="fa-solid fa-bold"></i>
                                        </button>
                                        <button data-command="italic" class="arcade-btn bg-surface hover:bg-blue-200 dark:hover:bg-blue-800 w-7 h-7 rounded text-xs italic flex items-center justify-center shrink-0" title="Italic (Ctrl+I)">
                                            <i class="fa-solid fa-italic"></i>
                                        </button>
                                        <button data-command="underline" class="arcade-btn bg-surface hover:bg-blue-200 dark:hover:bg-blue-800 w-7 h-7 rounded text-xs underline flex items-center justify-center shrink-0" title="Underline (Ctrl+U)">
                                            <i class="fa-solid fa-underline"></i>
                                        </button>
                                        <button data-command="strikeThrough" class="arcade-btn bg-surface hover:bg-blue-200 dark:hover:bg-blue-800 w-7 h-7 rounded text-xs line-through flex items-center justify-center shrink-0" title="Strikethrough">
                                            <i class="fa-solid fa-strikethrough"></i>
                                        </button>
                                        <button data-command="createLink" class="arcade-btn bg-surface hover:bg-blue-200 dark:hover:bg-blue-800 w-7 h-7 rounded text-xs flex items-center justify-center shrink-0" title="Sisipkan Tautan / Hyperlink">
                                            <i class="fa-solid fa-link"></i>
                                        </button>
                                        <button data-command="unlink" class="arcade-btn bg-surface hover:bg-blue-200 dark:hover:bg-blue-800 w-7 h-7 rounded text-xs flex items-center justify-center shrink-0" title="Hapus Tautan">
                                            <i class="fa-solid fa-link-slash"></i>
                                        </button>
                                    </div>

                                    <!-- Headings -->
                                    <div class="flex items-center gap-1 bg-surface-alt p-1 rounded-lg border border-black/20 dark:border-blue-900 shadow-[1px_1px_0px_#000] shrink-0">
                                        <button data-command="formatBlock" data-value="h1" class="arcade-btn bg-surface hover:bg-blue-200 dark:hover:bg-blue-800 px-1.5 h-7 rounded text-xs font-bold shrink-0" title="Heading 1">H1</button>
                                        <button data-command="formatBlock" data-value="h2" class="arcade-btn bg-surface hover:bg-blue-200 dark:hover:bg-blue-800 px-1.5 h-7 rounded text-xs font-bold shrink-0" title="Heading 2">H2</button>
                                        <button data-command="formatBlock" data-value="h3" class="arcade-btn bg-surface hover:bg-blue-200 dark:hover:bg-blue-800 px-1.5 h-7 rounded text-xs font-bold shrink-0" title="Heading 3">H3</button>
                                        <button data-command="formatBlock" data-value="p" class="arcade-btn bg-surface hover:bg-blue-200 dark:hover:bg-blue-800 px-1.5 h-7 rounded text-xs font-bold shrink-0" title="Paragraph">P</button>
                                    </div>
                                </div>

                                <!-- Right: Alignment & Clear -->
                                <div class="flex items-center gap-1 sm:gap-1.5 shrink-0 sm:shrink sm:ml-auto">
                                    <!-- Alignment -->
                                    <div class="flex items-center gap-1 bg-surface-alt p-1 rounded-lg border border-black/20 dark:border-blue-900 shadow-[1px_1px_0px_#000] shrink-0">
                                        <button data-command="justifyLeft" class="arcade-btn bg-surface hover:bg-blue-200 dark:hover:bg-blue-800 w-7 h-7 rounded text-xs flex items-center justify-center shrink-0" title="Align Left">
                                            <i class="fa-solid fa-align-left"></i>
                                        </button>
                                        <button data-command="justifyCenter" class="arcade-btn bg-surface hover:bg-blue-200 dark:hover:bg-blue-800 w-7 h-7 rounded text-xs flex items-center justify-center shrink-0" title="Align Center">
                                            <i class="fa-solid fa-align-center"></i>
                                        </button>
                                        <button data-command="justifyRight" class="arcade-btn bg-surface hover:bg-blue-200 dark:hover:bg-blue-800 w-7 h-7 rounded text-xs flex items-center justify-center shrink-0" title="Align Right">
                                            <i class="fa-solid fa-align-right"></i>
                                        </button>
                                    </div>

                                    <!-- Clear Formatting / Content -->
                                    <button id="clear-editor-btn" type="button" class="arcade-btn bg-surface hover:bg-rose-200 dark:hover:bg-rose-950 text-rose-600 dark:text-rose-400 font-bold px-2 h-7 rounded text-xs font-mono flex items-center justify-center shrink-0" title="Bersihkan format teks atau kosongkan catatan">
                                        <i class="fa-solid fa-broom mr-1"></i> CLEAR
                                    </button>
                                </div>
                            </div>

                            <!-- Toolbar Deck 2: Structural Blocks, Inserts, Studio Tools, Palette & Canvas Zoom -->
                            <div id="editor-deck-2" class="island-tool-deck flex items-center gap-1 sm:gap-1.5 pb-2 border-b border-black/10 dark:border-white/10 overflow-x-auto no-scrollbar scroll-smooth touch-pan-x flex-nowrap sm:flex-wrap w-full min-w-0">
                                <!-- Notepad Mode Active Banner (Visible only in Notepad mode) -->
                                <div id="notepad-mode-banner" class="hidden items-center gap-2 bg-emerald-100 dark:bg-emerald-950/70 border border-emerald-500/60 rounded-lg px-2.5 py-1 text-xs font-mono text-emerald-900 dark:text-emerald-300 shadow-[1px_1px_0px_#000] w-full">
                                    <i class="fa-solid fa-file-lines text-emerald-600 dark:text-emerald-400"></i>
                                    <span class="font-bold">MODE NOTEPAD</span>
                                    <span class="hidden md:inline text-black/60 dark:text-emerald-400/80">— Teks biasa murni tanpa format, siap disalin kapan saja.</span>
                                </div>

                                <div id="editor-deck-2-inserts" class="flex items-center gap-1 sm:gap-1.5 shrink-0 sm:shrink sm:flex-wrap">
                                    <!-- Lists & Structure -->
                                    <div class="flex items-center gap-0.5 sm:gap-1 bg-surface-alt p-0.5 sm:p-1 rounded-lg border border-black/20 dark:border-blue-900 shadow-[1px_1px_0px_#000] shrink-0">
                                        <button data-command="insertUnorderedList" class="arcade-btn bg-surface hover:bg-blue-200 dark:hover:bg-blue-800 w-7 h-7 rounded text-xs flex items-center justify-center shrink-0" title="Bullet List">
                                            <i class="fa-solid fa-list-ul"></i>
                                        </button>
                                        <button data-command="insertOrderedList" class="arcade-btn bg-surface hover:bg-blue-200 dark:hover:bg-blue-800 w-7 h-7 rounded text-xs flex items-center justify-center shrink-0" title="Numbered List">
                                            <i class="fa-solid fa-list-ol"></i>
                                        </button>
                                        <button data-command="formatBlock" data-value="blockquote" class="arcade-btn bg-surface hover:bg-blue-200 dark:hover:bg-blue-800 w-7 h-7 rounded text-xs flex items-center justify-center shrink-0" title="Quote Block">
                                            <i class="fa-solid fa-quote-left"></i>
                                        </button>
                                        <button data-command="formatBlock" data-value="pre" class="arcade-btn bg-surface hover:bg-blue-200 dark:hover:bg-blue-800 w-7 h-7 rounded text-xs font-mono flex items-center justify-center shrink-0" title="Code Block">
                                            <i class="fa-solid fa-code"></i>
                                        </button>
                                        <button data-command="insertHorizontalRule" class="arcade-btn bg-surface hover:bg-blue-200 dark:hover:bg-blue-800 w-7 h-7 rounded text-xs flex items-center justify-center shrink-0" title="Divider Line">
                                            <i class="fa-solid fa-minus"></i>
                                        </button>
                                    </div>

                                    <!-- Content Inserts -->
                                    <div class="flex items-center gap-0.5 sm:gap-1 bg-surface-alt p-0.5 sm:p-1 rounded-lg border border-black/20 dark:border-blue-900 shadow-[1px_1px_0px_#000] shrink-0">
                                        <button id="insert-table-btn"
                                                class="arcade-btn bg-blue-600 hover:bg-blue-500 text-white px-2 sm:px-2.5 h-7 rounded text-xs font-mono flex items-center justify-center shrink-0"
                                                title="Insert 3x4 Retro Data Table">
                                            <i class="fa-solid fa-table mr-1"></i> TABLE
                                        </button>
                                        <button id="insert-task-btn"
                                                class="arcade-btn bg-blue-700 hover:bg-blue-600 text-white px-2 sm:px-2.5 h-7 rounded text-xs font-mono flex items-center justify-center shrink-0"
                                                title="Insert Interactive Quest Checklist">
                                            <i class="fa-solid fa-square-check mr-1"></i> TASK
                                        </button>
                                        <button id="insert-media-btn"
                                                class="arcade-btn bg-surface hover:bg-blue-200 dark:hover:bg-blue-800 px-2 sm:px-2.5 h-7 rounded text-xs font-mono flex items-center justify-center shrink-0"
                                                title="Sisipkan Gambar atau Artefak Pixel">
                                            <i class="fa-solid fa-image mr-1 text-blue-600 dark:text-cyan-400"></i> IMG
                                        </button>
                                        <input type="file" id="media-file-input" accept="image/*" class="hidden">
                                    </div>
                                </div>

                                <!-- Right: Color Palette, Studio Tools & Canvas Zoom -->
                                <div class="flex items-center gap-1 sm:gap-1.5 shrink-0 sm:shrink sm:ml-auto">
                                    <!-- Complete Color Palette (Text Colors, Highlights & Custom Pickers) -->
                                    <div id="editor-deck-2-palette" class="flex items-center gap-1 bg-surface-alt p-1 rounded-lg border border-black/20 dark:border-blue-900 shadow-[1px_1px_0px_#000] px-1.5 h-7 shrink-0" title="Palet Pewarnaan Naskah & Teks">
                                        <span class="text-[10px] font-mono font-bold text-slate-500 mr-0.5 shrink-0" title="Warna Font"><i class="fa-solid fa-palette text-blue-500"></i></span>
                                        <!-- Black (User explicitly requested!) -->
                                        <button data-color="#000000" data-color-type="foreColor" class="w-3.5 h-3.5 rounded-full bg-black border border-black/50 cursor-pointer hover:scale-125 transition-transform shrink-0 shadow-xs" title="Hitam Pekat (#000000)"></button>
                                        <!-- Charcoal -->
                                        <button data-color="#1f2937" data-color-type="foreColor" class="w-3.5 h-3.5 rounded-full bg-gray-800 border border-black/50 cursor-pointer hover:scale-125 transition-transform shrink-0" title="Dark Charcoal (#1f2937)"></button>
                                        <!-- Slate Gray -->
                                        <button data-color="#64748b" data-color-type="foreColor" class="w-3.5 h-3.5 rounded-full bg-slate-500 border border-black/50 cursor-pointer hover:scale-125 transition-transform shrink-0" title="Slate Gray (#64748b)"></button>
                                        <!-- White / Light -->
                                        <button data-color="#f8fafc" data-color-type="foreColor" class="w-3.5 h-3.5 rounded-full bg-slate-100 border border-black/50 cursor-pointer hover:scale-125 transition-transform shrink-0" title="Putih (#f8fafc)"></button>
                                        <!-- Crimson Red -->
                                        <button data-color="#ef4444" data-color-type="foreColor" class="w-3.5 h-3.5 rounded-full bg-rose-500 border border-black/50 cursor-pointer hover:scale-125 transition-transform shrink-0" title="Merah Crimson (#ef4444)"></button>
                                        <!-- Flame Orange -->
                                        <button data-color="#f97316" data-color-type="foreColor" class="w-3.5 h-3.5 rounded-full bg-orange-500 border border-black/50 cursor-pointer hover:scale-125 transition-transform shrink-0" title="Jingga / Retro Orange (#f97316)"></button>
                                        <!-- Pixel Gold / Amber -->
                                        <button data-color="#f59e0b" data-color-type="foreColor" class="w-3.5 h-3.5 rounded-full bg-amber-500 border border-black/50 cursor-pointer hover:scale-125 transition-transform shrink-0" title="Pixel Gold / Amber (#f59e0b)"></button>
                                        <!-- Lime Green -->
                                        <button data-color="#84cc16" data-color-type="foreColor" class="w-3.5 h-3.5 rounded-full bg-lime-500 border border-black/50 cursor-pointer hover:scale-125 transition-transform shrink-0" title="Hijau Lime (#84cc16)"></button>
                                        <!-- Emerald Green -->
                                        <button data-color="#10b981" data-color-type="foreColor" class="w-3.5 h-3.5 rounded-full bg-emerald-500 border border-black/50 cursor-pointer hover:scale-125 transition-transform shrink-0" title="Emerald Glow (#10b981)"></button>
                                        <!-- Neon Cyan -->
                                        <button data-color="#06b6d4" data-color-type="foreColor" class="w-3.5 h-3.5 rounded-full bg-cyan-500 border border-black/50 cursor-pointer hover:scale-125 transition-transform shrink-0" title="Neon Cyan (#06b6d4)"></button>
                                        <!-- Arcade Blue -->
                                        <button data-color="#3b82f6" data-color-type="foreColor" class="w-3.5 h-3.5 rounded-full bg-blue-500 border border-black/50 cursor-pointer hover:scale-125 transition-transform shrink-0" title="Arcade Blue (#3b82f6)"></button>
                                        <!-- Mystic Purple -->
                                        <button data-color="#a855f7" data-color-type="foreColor" class="w-3.5 h-3.5 rounded-full bg-purple-500 border border-black/50 cursor-pointer hover:scale-125 transition-transform shrink-0" title="Mystic Purple (#a855f7)"></button>
                                        <!-- Retro Pink / Magenta -->
                                        <button data-color="#ec4899" data-color-type="foreColor" class="w-3.5 h-3.5 rounded-full bg-pink-500 border border-black/50 cursor-pointer hover:scale-125 transition-transform shrink-0" title="Retro Pink / Magenta (#ec4899)"></button>
                                        <!-- Custom Text Color Input -->
                                        <label class="relative inline-flex items-center cursor-pointer shrink-0" title="Pilih Warna Teks Bebas (RGB Custom Color)">
                                            <input type="color" id="custom-text-color-picker" value="#000000" class="w-3.5 h-3.5 rounded-full cursor-pointer border border-black/50 p-0 overflow-hidden opacity-0 absolute inset-0">
                                            <span class="w-3.5 h-3.5 rounded-full bg-gradient-to-tr from-rose-500 via-amber-400 via-emerald-400 to-blue-500 border border-black/50 hover:scale-125 transition-transform block"></span>
                                        </label>

                                        <span class="text-black/30 dark:text-white/30 text-xs mx-0.5">|</span>

                                        <!-- Highlight (hiliteColor) Swatches -->
                                        <span class="text-[10px] font-mono font-bold text-slate-500 shrink-0" title="Sorotan / Highlight Teks"><i class="fa-solid fa-highlighter text-yellow-500"></i></span>
                                        <!-- Highlight Yellow -->
                                        <button data-color="#fef08a" data-color-type="hiliteColor" class="w-3.5 h-3.5 rounded-sm bg-yellow-200 border border-yellow-500/80 cursor-pointer hover:scale-125 transition-transform shrink-0" title="Sorotan Kuning (#fef08a)"></button>
                                        <!-- Highlight Green -->
                                        <button data-color="#bbf7d0" data-color-type="hiliteColor" class="w-3.5 h-3.5 rounded-sm bg-emerald-200 border border-emerald-500/80 cursor-pointer hover:scale-125 transition-transform shrink-0" title="Sorotan Hijau (#bbf7d0)"></button>
                                        <!-- Highlight Cyan -->
                                        <button data-color="#bae6fd" data-color-type="hiliteColor" class="w-3.5 h-3.5 rounded-sm bg-cyan-200 border border-cyan-500/80 cursor-pointer hover:scale-125 transition-transform shrink-0" title="Sorotan Biru Muda (#bae6fd)"></button>
                                        <!-- Highlight Pink -->
                                        <button data-color="#fbcfe8" data-color-type="hiliteColor" class="w-3.5 h-3.5 rounded-sm bg-pink-200 border border-pink-500/80 cursor-pointer hover:scale-125 transition-transform shrink-0" title="Sorotan Merah Muda (#fbcfe8)"></button>
                                        <!-- Clear Highlight -->
                                        <button data-color="transparent" data-color-type="hiliteColor" class="w-3.5 h-3.5 rounded-sm bg-transparent border border-dashed border-red-500 flex items-center justify-center cursor-pointer hover:scale-125 transition-transform shrink-0 text-[8px] text-red-500 font-bold" title="Hapus Sorotan / Clear Highlight">✕</button>
                                        <!-- Custom Highlight Picker -->
                                        <label class="relative inline-flex items-center cursor-pointer shrink-0" title="Pilih Warna Highlight Bebas (RGB Custom Highlight)">
                                            <input type="color" id="custom-hilite-color-picker" value="#fef08a" class="w-3.5 h-3.5 rounded-sm cursor-pointer border border-black/50 p-0 overflow-hidden opacity-0 absolute inset-0">
                                            <span class="w-3.5 h-3.5 rounded-sm bg-gradient-to-tr from-yellow-200 via-pink-200 to-cyan-200 border border-black/50 hover:scale-125 transition-transform block"></span>
                                        </label>
                                    </div>

                                    <!-- Studio Markdown & Plain Text Tools (AI removed completely) -->
                                    <div class="flex items-center gap-0.5 sm:gap-1 bg-surface-alt p-0.5 sm:p-1 rounded-lg border border-black/20 dark:border-blue-900 shadow-[1px_1px_0px_#000] shrink-0">
                                        <button id="markdown-paste-btn"
                                                class="arcade-btn bg-cyan-600 hover:bg-cyan-500 text-white px-2 sm:px-2.5 h-7 rounded text-xs font-mono flex items-center justify-center shrink-0"
                                                title="Paste or Convert Raw Markdown (.md)">
                                            <i class="fa-solid fa-file-code mr-1"></i> MD
                                        </button>
                                        <button id="markdown-view-toggle-btn"
                                                class="arcade-btn bg-surface hover:bg-cyan-200 dark:hover:bg-cyan-900/40 px-2 sm:px-2.5 h-7 rounded text-xs font-mono flex items-center justify-center shrink-0"
                                                title="Toggle Raw Markdown Textarea View">
                                            <i class="fa-brands fa-markdown mr-1 text-blue-600 dark:text-cyan-400"></i> RAW
                                        </button>
                                        <button id="plain-text-toggle-btn"
                                                class="arcade-btn bg-surface hover:bg-emerald-200 dark:hover:bg-emerald-900/40 px-2 sm:px-2.5 h-7 rounded text-xs font-mono flex items-center justify-center shrink-0"
                                                title="Mode Teks Biasa / Notepad (Alt+N)">
                                            <i class="fa-solid fa-file-lines mr-1 text-emerald-600 dark:text-emerald-400"></i> TXT
                                        </button>
                                        <button id="find-replace-toggle-btn"
                                                class="arcade-btn bg-surface hover:bg-amber-200 dark:hover:bg-amber-900/40 px-2 sm:px-2.5 h-7 rounded text-xs font-mono flex items-center justify-center shrink-0"
                                                title="Find & Replace in Scroll (Ctrl+F)">
                                            <i class="fa-solid fa-magnifying-glass mr-1 text-amber-600 dark:text-yellow-400"></i> FIND
                                        </button>
                                    </div>

                                    <!-- Canvas Zoom Widget -->
                                    <div class="flex items-center bg-surface rounded border border-black/20 dark:border-blue-800 px-1 sm:px-1.5 h-7 shadow-[1px_1px_0px_#000] shrink-0">
                                        <button id="zoom-out-btn"
                                                class="text-xs hover:text-blue-600 dark:hover:text-cyan-400 px-1 cursor-pointer shrink-0"
                                                title="Zoom Out Canvas">
                                            <i class="fa-solid fa-minus text-[10px]"></i>
                                        </button>
                                        <button id="zoom-reset-btn"
                                                class="px-1.5 text-[11px] font-mono font-bold hover:text-blue-600 dark:hover:text-cyan-400 cursor-pointer shrink-0"
                                                title="Click to Reset Zoom (100%)">
                                            <span id="zoom-level-display">100%</span>
                                        </button>
                                        <button id="zoom-in-btn"
                                                class="text-xs hover:text-blue-600 dark:hover:text-cyan-400 px-1 cursor-pointer shrink-0"
                                                title="Zoom In Canvas">
                                            <i class="fa-solid fa-plus text-[10px]"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Toolbar Deck 3: Operations & Actions Deck (Arsip, Ekspor, Kategori & Manajemen) -->
                            <div id="editor-deck-operations" class="island-tool-deck flex items-center justify-between gap-1.5 sm:gap-2 overflow-x-auto no-scrollbar scroll-smooth touch-pan-x flex-nowrap sm:flex-wrap w-full min-w-0 pt-0.5">
                                <!-- Category Quick Tools -->
                                <div class="flex items-center gap-1 bg-surface-alt px-2 py-1 rounded-lg border border-black/20 dark:border-blue-900 shadow-[1px_1px_0px_#000] shrink-0">
                                    <span class="text-[10px] font-mono font-bold text-slate-500 uppercase mr-1">Kategori:</span>
                                    <button type="button"
                                            id="quick-edit-category-btn"
                                            class="arcade-btn bg-surface hover:bg-cyan-200 dark:hover:bg-blue-900 text-cyan-600 dark:text-cyan-400 w-6 h-6 flex items-center justify-center rounded text-[10px]"
                                            title="Edit Selected Category">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <button type="button"
                                            id="quick-delete-category-btn"
                                            class="arcade-btn bg-surface hover:bg-rose-200 dark:hover:bg-rose-950 text-rose-500 dark:text-rose-400 w-6 h-6 flex items-center justify-center rounded text-[10px]"
                                            title="Delete Selected Category">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                    <button type="button"
                                            id="quick-forge-category-btn"
                                            class="arcade-btn bg-surface hover:bg-emerald-200 dark:hover:bg-emerald-950 text-emerald-600 dark:text-emerald-400 w-6 h-6 flex items-center justify-center rounded text-[10px]"
                                            title="Forge New Category">
                                        <i class="fa-solid fa-plus"></i>
                                    </button>
                                </div>

                                <!-- Action Buttons Group: Duplicate, Export, Zen, Archive, Delete -->
                                <div class="flex items-center gap-1.5 shrink-0 ml-auto">
                                    <!-- Duplicate Button -->
                                    <button id="duplicate-note-btn"
                                            class="arcade-btn bg-surface hover:bg-cyan-100 dark:hover:bg-cyan-900/40 px-2 sm:px-2.5 h-7 rounded-lg text-xs font-mono flex items-center gap-1 shrink-0"
                                            title="Duplicate Scroll">
                                        <i class="fa-solid fa-clone text-blue-600 dark:text-cyan-400"></i>
                                        <span class="hidden md:inline">Duplikat</span>
                                    </button>

                                    <!-- Export Dropdown -->
                                    <div class="relative inline-block text-left shrink-0" id="export-dropdown-wrapper">
                                        <button id="export-note-btn"
                                                class="arcade-btn bg-surface hover:bg-blue-200 dark:hover:bg-blue-800 px-2 sm:px-2.5 h-7 rounded-lg text-xs font-mono flex items-center gap-1"
                                                title="Export Scroll">
                                            <i class="fa-solid fa-file-arrow-down text-blue-600 dark:text-blue-400"></i>
                                            <span class="hidden md:inline">Ekspor</span>
                                        </button>
                                        <div id="export-menu" class="hidden absolute right-0 mt-1 w-44 bg-[var(--bg-surface)] border-2 border-black rounded-lg shadow-[3px_3px_0px_#000] z-50 p-1 font-mono text-xs">
                                            <button data-export-format="md" class="w-full text-left px-2 py-1 rounded hover:bg-blue-600 hover:text-white flex items-center gap-2">
                                                <i class="fa-brands fa-markdown text-blue-600 dark:text-blue-400"></i> Markdown (.md)
                                            </button>
                                            <button data-export-format="html" class="w-full text-left px-2 py-1 rounded hover:bg-blue-600 hover:text-white flex items-center gap-2">
                                                <i class="fa-solid fa-code text-blue-600 dark:text-cyan-400"></i> HTML (.html)
                                            </button>
                                            <button data-export-format="txt" class="w-full text-left px-2 py-1 rounded hover:bg-blue-600 hover:text-white flex items-center gap-2">
                                                <i class="fa-solid fa-file-lines text-amber-600 dark:text-amber-400"></i> Text (.txt)
                                            </button>
                                            <button data-export-format="csv" class="w-full text-left px-2 py-1 rounded hover:bg-blue-600 hover:text-white flex items-center gap-2">
                                                <i class="fa-solid fa-file-csv text-emerald-600 dark:text-emerald-400"></i> CSV Table (.csv)
                                            </button>
                                            <button data-export-format="json" class="w-full text-left px-2 py-1 rounded hover:bg-blue-600 hover:text-white flex items-center gap-2">
                                                <i class="fa-solid fa-file-code text-cyan-600 dark:text-cyan-400"></i> JSON Backup (.json)
                                            </button>
                                            <div class="border-t border-black/20 my-1"></div>
                                            <button id="copy-plain-btn" class="w-full text-left px-2 py-1 rounded hover:bg-blue-600 hover:text-white flex items-center gap-2">
                                                <i class="fa-solid fa-file-lines text-amber-600 dark:text-amber-400"></i> Copy Plain Text
                                            </button>
                                            <button id="copy-markdown-btn" class="w-full text-left px-2 py-1 rounded hover:bg-blue-600 hover:text-white flex items-center gap-2">
                                                <i class="fa-solid fa-copy text-blue-600 dark:text-cyan-400"></i> Copy Markdown
                                            </button>
                                            <button id="copy-html-btn" class="w-full text-left px-2 py-1 rounded hover:bg-blue-600 hover:text-white flex items-center gap-2">
                                                <i class="fa-solid fa-code text-emerald-600 dark:text-emerald-400"></i> Copy HTML
                                            </button>
                                            <button id="copy-json-btn" class="w-full text-left px-2 py-1 rounded hover:bg-blue-600 hover:text-white flex items-center gap-2">
                                                <i class="fa-solid fa-cube text-purple-600 dark:text-purple-400"></i> Copy JSON
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Zen Mode Toggle -->
                                    <button id="zen-mode-btn"
                                            class="arcade-btn bg-surface hover:bg-purple-100 dark:hover:bg-purple-900/40 px-2 sm:px-2.5 h-7 rounded-lg text-xs font-mono flex items-center gap-1 shrink-0"
                                            title="Toggle Distraction-Free Zen Writing Mode">
                                        <i class="fa-solid fa-expand text-purple-600 dark:text-purple-400"></i>
                                        <span class="hidden md:inline">Zen</span>
                                    </button>

                                    <!-- Archive Button -->
                                    <button id="archive-note-btn"
                                            class="arcade-btn bg-surface hover:bg-amber-100 dark:hover:bg-amber-900/40 px-2 sm:px-2.5 h-7 rounded-lg text-xs font-mono flex items-center gap-1 shrink-0"
                                            title="Archive Scroll to Library Vault">
                                        <i class="fa-solid fa-box-archive text-amber-600 dark:text-amber-400"></i>
                                        <span class="hidden md:inline">Arsip</span>
                                    </button>

                                    <!-- Delete Scroll Button -->
                                    <button id="delete-note-btn"
                                            class="arcade-btn bg-rose-600 hover:bg-rose-500 text-white px-2.5 sm:px-3 h-7 rounded-lg text-xs font-mono font-bold tracking-wider flex items-center justify-center shrink-0"
                                            title="Banish Scroll to Digital Void">
                                        <i class="fa-solid fa-trash-can mr-1"></i> DELETE
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Find & Replace Bar -->
                <div id="find-replace-bar" class="shrink-0 hidden p-2 bg-surface-alt border-2 border-black dark:border-blue-700 rounded-lg flex items-center justify-between gap-2 flex-wrap shadow-[3px_3px_0px_#000] text-xs font-mono">
                    <div class="flex items-center gap-2 flex-wrap">
                        <div class="flex items-center gap-1 bg-surface px-2 py-1 rounded border border-black/30">
                            <i class="fa-solid fa-magnifying-glass text-blue-600 dark:text-cyan-400"></i>
                            <input id="find-input" type="text" placeholder="Find in scroll..." class="bg-transparent border-0 focus:outline-none w-32 sm:w-44 text-xs">
                        </div>
                        <div class="flex items-center gap-1 bg-surface px-2 py-1 rounded border border-black/30">
                            <i class="fa-solid fa-repeat text-amber-600 dark:text-amber-400"></i>
                            <input id="replace-input" type="text" placeholder="Replace with..." class="bg-transparent border-0 focus:outline-none w-32 sm:w-44 text-xs">
                        </div>
                        <span id="find-match-badge" class="px-2 py-0.5 rounded bg-blue-100 dark:bg-blue-900/40 text-blue-950 dark:text-blue-100 text-[10px] font-bold">0 matches</span>
                        <button id="find-prev-btn" class="arcade-btn bg-surface px-2 py-0.5 rounded text-[10px]" title="Previous match"><i class="fa-solid fa-arrow-up"></i></button>
                        <button id="find-next-btn" class="arcade-btn bg-surface px-2 py-0.5 rounded text-[10px]" title="Next match"><i class="fa-solid fa-arrow-down"></i></button>
                        <button id="replace-one-btn" class="arcade-btn bg-amber-600 hover:bg-amber-500 text-white px-2 py-0.5 rounded text-[10px]">Replace</button>
                        <button id="replace-all-btn" class="arcade-btn bg-blue-600 hover:bg-blue-500 text-white px-2 py-0.5 rounded text-[10px]">Replace All</button>
                    </div>
                    <button id="close-find-bar-btn" class="arcade-btn bg-red-600 hover:bg-red-500 text-white px-2 py-0.5 rounded text-[10px]" title="Close Find Bar">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <!-- Detailed Table Customization Matrix Toolkit -->
                <div id="table-toolkit-bar" class="shrink-0 hidden p-2 bg-surface-alt border-2 border-black dark:border-blue-700 rounded-lg flex items-center justify-between gap-2 flex-wrap shadow-[3px_3px_0px_#000] text-xs font-mono select-none transition-all">
                    <!-- Left: Table Info & Row/Col Operations -->
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <div class="flex items-center gap-1.5 px-2 py-1 rounded bg-blue-100 dark:bg-blue-950/80 border border-blue-400 dark:border-blue-700 text-blue-900 dark:text-cyan-300">
                            <i class="fa-solid fa-table text-blue-600 dark:text-cyan-400"></i>
                            <span class="font-pixel text-[10px] hidden sm:inline">TABLE MATRIX</span>
                            <span id="table-cell-coord" class="font-bold text-[10px] font-mono text-cyan-700 dark:text-cyan-300">R1:C1</span>
                        </div>

                        <!-- Row Actions Group -->
                        <div class="flex items-center gap-0.5 bg-surface p-0.5 rounded border border-black/20 dark:border-blue-900">
                            <button id="table-add-row-above-btn" type="button" class="arcade-btn bg-surface hover:bg-blue-100 dark:hover:bg-blue-900/60 px-2 py-1 rounded text-[11px]" title="Tambah Baris di Atas">
                                <i class="fa-solid fa-arrow-up text-cyan-600 dark:text-cyan-400 sm:mr-1"></i><span class="hidden md:inline">+Baris ↑</span>
                            </button>
                            <button id="table-add-row-below-btn" type="button" class="arcade-btn bg-surface hover:bg-blue-100 dark:hover:bg-blue-900/60 px-2 py-1 rounded text-[11px]" title="Tambah Baris di Bawah">
                                <i class="fa-solid fa-arrow-down text-cyan-600 dark:text-cyan-400 sm:mr-1"></i><span class="hidden md:inline">+Baris ↓</span>
                            </button>
                            <button id="table-del-row-btn" type="button" class="arcade-btn bg-surface hover:bg-red-100 dark:hover:bg-red-950/50 text-red-600 dark:text-red-400 px-2 py-1 rounded text-[11px]" title="Hapus Baris Aktif">
                                <i class="fa-solid fa-trash-can sm:mr-1"></i><span class="hidden md:inline">-Baris</span>
                            </button>
                        </div>

                        <!-- Column Actions Group -->
                        <div class="flex items-center gap-0.5 bg-surface p-0.5 rounded border border-black/20 dark:border-blue-900">
                            <button id="table-add-col-left-btn" type="button" class="arcade-btn bg-surface hover:bg-blue-100 dark:hover:bg-blue-900/60 px-2 py-1 rounded text-[11px]" title="Tambah Kolom di Kiri">
                                <i class="fa-solid fa-arrow-left text-blue-600 dark:text-blue-400 sm:mr-1"></i><span class="hidden md:inline">+Kolom ←</span>
                            </button>
                            <button id="table-add-col-right-btn" type="button" class="arcade-btn bg-surface hover:bg-blue-100 dark:hover:bg-blue-900/60 px-2 py-1 rounded text-[11px]" title="Tambah Kolom di Kanan">
                                <i class="fa-solid fa-arrow-right text-blue-600 dark:text-blue-400 sm:mr-1"></i><span class="hidden md:inline">+Kolom →</span>
                            </button>
                            <button id="table-del-col-btn" type="button" class="arcade-btn bg-surface hover:bg-red-100 dark:hover:bg-red-950/50 text-red-600 dark:text-red-400 px-2 py-1 rounded text-[11px]" title="Hapus Kolom Aktif">
                                <i class="fa-solid fa-trash-can sm:mr-1"></i><span class="hidden md:inline">-Kolom</span>
                            </button>
                        </div>

                        <!-- Header Toggle & Alignment -->
                        <div class="flex items-center gap-0.5 bg-surface p-0.5 rounded border border-black/20 dark:border-blue-900">
                            <button id="table-toggle-header-btn" type="button" class="arcade-btn bg-surface hover:bg-amber-100 dark:hover:bg-amber-950/50 text-amber-600 dark:text-amber-400 px-2 py-1 rounded text-[11px]" title="Toggle Header (TH / TD)">
                                <i class="fa-solid fa-heading sm:mr-1"></i><span class="hidden lg:inline">TH/TD</span>
                            </button>
                            <button id="table-align-left-btn" type="button" class="arcade-btn bg-surface hover:bg-blue-100 dark:hover:bg-blue-900/60 px-1.5 py-1 rounded text-[11px]" title="Rata Kiri">
                                <i class="fa-solid fa-align-left"></i>
                            </button>
                            <button id="table-align-center-btn" type="button" class="arcade-btn bg-surface hover:bg-blue-100 dark:hover:bg-blue-900/60 px-1.5 py-1 rounded text-[11px]" title="Rata Tengah">
                                <i class="fa-solid fa-align-center"></i>
                            </button>
                            <button id="table-align-right-btn" type="button" class="arcade-btn bg-surface hover:bg-blue-100 dark:hover:bg-blue-900/60 px-1.5 py-1 rounded text-[11px]" title="Rata Kanan">
                                <i class="fa-solid fa-align-right"></i>
                            </button>
                        </div>

                        <!-- Cell Color Picker & Text Color -->
                        <div class="flex items-center gap-1.5 bg-surface px-2 py-0.5 rounded border border-black/20 dark:border-blue-900">
                            <!-- Cell Bg Color Picker -->
                            <label class="flex items-center gap-1 cursor-pointer" title="Warna Latar Sel (Background Color)">
                                <i class="fa-solid fa-fill-drip text-[10px] text-cyan-600 dark:text-cyan-400"></i>
                                <input id="table-cell-bg-picker" type="color" value="#1e3a8a" class="w-4 h-4 p-0 border-0 rounded cursor-pointer bg-transparent">
                            </label>
                            <!-- Cell Text Color Picker -->
                            <label class="flex items-center gap-1 cursor-pointer" title="Warna Teks Sel (Text Color)">
                                <i class="fa-solid fa-font text-[10px] text-amber-500"></i>
                                <input id="table-cell-text-picker" type="color" value="#ffffff" class="w-4 h-4 p-0 border-0 rounded cursor-pointer bg-transparent">
                            </label>
                            <button id="table-clear-colors-btn" type="button" class="arcade-btn bg-surface hover:bg-slate-200 dark:hover:bg-slate-800 text-[10px] px-1 py-0.5 rounded" title="Reset Warna Sel ke Normal">
                                <i class="fa-solid fa-rotate-left"></i>
                            </button>
                        </div>

                        <!-- Insert / Paste Image in Active Cell -->
                        <div class="flex items-center gap-1">
                            <button id="table-insert-img-btn" type="button" class="arcade-btn bg-emerald-600 hover:bg-emerald-500 text-white px-2.5 py-1 rounded text-[11px] font-mono shadow-[1px_1px_0px_#000]" title="Sisipkan Gambar ke Sel Aktif">
                                <i class="fa-solid fa-file-image mr-1 text-emerald-200"></i>+Gambar Sel
                            </button>
                            <input id="table-cell-img-input" type="file" accept="image/*" class="hidden">
                        </div>
                    </div>

                    <!-- Right: Delete Table & Close Toolbar -->
                    <div class="flex items-center gap-1">
                        <button id="table-delete-table-btn" type="button" class="arcade-btn bg-red-600 hover:bg-red-500 text-white px-2.5 py-1 rounded text-[11px] font-mono shadow-[1px_1px_0px_#000]" title="Hapus Keseluruhan Tabel">
                            <i class="fa-solid fa-trash mr-1"></i>Hapus Tabel
                        </button>
                        <button id="table-close-toolkit-btn" type="button" class="arcade-btn bg-surface hover:bg-slate-200 dark:hover:bg-slate-800 px-2 py-1 rounded text-[10px]" title="Tutup Toolbar Tabel">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                </div>

                <!-- Detailed Image Artifact Matrix Toolkit -->
                <div id="image-toolkit-bar" class="shrink-0 hidden p-2 bg-surface-alt border-2 border-black dark:border-blue-700 rounded-lg flex items-center justify-between gap-2 flex-wrap shadow-[3px_3px_0px_#000] text-xs font-mono select-none transition-all">
                    <!-- Left: Image Info & Alignment Operations -->
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <div class="flex items-center gap-1.5 px-2 py-1 rounded bg-blue-100 dark:bg-blue-950/80 border border-blue-400 dark:border-blue-700 text-blue-900 dark:text-cyan-300">
                            <i class="fa-solid fa-image text-blue-600 dark:text-cyan-400"></i>
                            <span class="font-pixel text-[10px] hidden sm:inline">IMAGE MATRIX</span>
                            <span id="image-align-badge" class="font-bold text-[10px] font-mono text-cyan-700 dark:text-cyan-300 uppercase">CENTER</span>
                        </div>

                        <!-- Alignment Actions Group -->
                        <div class="flex items-center gap-0.5 bg-surface p-0.5 rounded border border-black/20 dark:border-blue-900">
                            <button id="img-align-left-btn" type="button" class="arcade-btn bg-surface hover:bg-blue-100 dark:hover:bg-blue-900/60 px-2 py-1 rounded text-[11px]" title="Ratakan Gambar ke Kiri">
                                <i class="fa-solid fa-align-left text-cyan-600 dark:text-cyan-400 sm:mr-1"></i><span class="hidden md:inline">Kiri</span>
                            </button>
                            <button id="img-align-center-btn" type="button" class="arcade-btn bg-surface hover:bg-blue-100 dark:hover:bg-blue-900/60 px-2 py-1 rounded text-[11px]" title="Ratakan Gambar ke Tengah">
                                <i class="fa-solid fa-align-center text-cyan-600 dark:text-cyan-400 sm:mr-1"></i><span class="hidden md:inline">Tengah</span>
                            </button>
                            <button id="img-align-right-btn" type="button" class="arcade-btn bg-surface hover:bg-blue-100 dark:hover:bg-blue-900/60 px-2 py-1 rounded text-[11px]" title="Ratakan Gambar ke Kanan">
                                <i class="fa-solid fa-align-right text-cyan-600 dark:text-cyan-400 sm:mr-1"></i><span class="hidden md:inline">Kanan</span>
                            </button>
                        </div>

                        <!-- Caption Actions Group -->
                        <div class="flex items-center gap-1">
                            <button id="img-caption-btn" type="button" class="arcade-btn bg-surface hover:bg-amber-100 dark:hover:bg-amber-950/60 text-amber-700 dark:text-amber-400 px-2.5 py-1 rounded text-[11px] font-mono border border-amber-300 dark:border-amber-700/60 shadow-[1px_1px_0px_#000]" title="Beri atau Ubah Caption Gambar">
                                <i class="fa-solid fa-comment-dots mr-1"></i><span class="inline">Beri Caption</span>
                            </button>
                        </div>
                    </div>

                    <!-- Right: Delete Image & Close Toolbar -->
                    <div class="flex items-center gap-1">
                        <button id="img-delete-btn" type="button" class="arcade-btn bg-red-600 hover:bg-red-500 text-white px-2.5 py-1 rounded text-[11px] font-mono shadow-[1px_1px_0px_#000]" title="Hapus Gambar Terpilih">
                            <i class="fa-solid fa-trash mr-1"></i>Hapus Gambar
                        </button>
                        <button id="close-image-bar-btn" type="button" class="arcade-btn bg-surface hover:bg-slate-200 dark:hover:bg-slate-800 px-2 py-1 rounded text-[10px]" title="Tutup Toolbar Gambar">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                </div>

                <!-- Zoomable Editable Content Area (The Core Canvas - Full Size) -->
                <div id="editor-canvas-card" class="arcade-card flex-1 min-h-[550px] lg:min-h-[700px] p-4 sm:p-6 lg:p-8 rounded-2xl overflow-y-auto overflow-x-auto relative shadow-[4px_4px_0px_#000] bg-[var(--bg-surface)] flex flex-col">
                    <div id="editor-zoom-wrapper" class="zoom-canvas-container min-h-full flex flex-col">
                        <!-- Rich Editable Canvas -->
                        <div id="editor-content"
                             contenteditable="{{ $activeNote ? 'true' : 'false' }}"
                             spellcheck="false"
                             class="notenux-editor prose dark:prose-invert max-w-none focus:outline-none min-h-[350px] flex-1 break-words overflow-x-hidden"
                             style="font-family: 'Modern Sans', sans-serif; font-size: 16px;">
                            @if($activeNote)
                                {!! $activeNote->content !!}
                            @else
                                <div class="flex flex-col items-center justify-center min-h-[300px] text-center text-slate-400 p-8 space-y-3 font-mono select-none">
                                    <i class="fa-solid fa-scroll text-4xl text-blue-500/40"></i>
                                    <p class="font-bold text-sm text-slate-600 dark:text-slate-300">Belum ada catatan.</p>
                                    <p class="text-xs">Klik tombol di bawah atau tekan <kbd class="px-1.5 py-0.5 rounded bg-slate-200 dark:bg-slate-800 border text-[10px]">Ctrl+N</kbd> untuk mulai membuat catatan baru.</p>
                                    <button type="button" id="empty-state-new-btn" class="arcade-btn bg-blue-600 hover:bg-blue-500 text-white font-mono text-xs px-4 py-2 rounded shadow-[2px_2px_0px_#000] cursor-pointer mt-2 inline-flex items-center gap-2">
                                        <i class="fa-solid fa-plus"></i> Buat Catatan Baru
                                    </button>
                                </div>
                            @endif
                        </div>

                        <!-- Raw Markdown Editor Textarea (Hidden by default, toggleable via RAW MD button) -->
                        <textarea id="markdown-raw-editor"
                                  spellcheck="false"
                                  class="hidden w-full h-[500px] p-4 font-mono text-sm bg-black/80 text-cyan-300 border-2 border-cyan-500 rounded-lg focus:outline-none leading-relaxed resize-y"></textarea>

                        <!-- Plain Text / Notepad Mode Container (Hidden by default, toggleable via TXT button or Alt+N) -->
                        <div id="plain-text-container" class="hidden flex flex-col flex-1 min-h-[420px]">
                            <!-- Notepad Top Bar: Mode Badge, Real-time Stats & Dedicated Copy All Button -->
                            <div class="bg-surface-alt border-2 border-black dark:border-emerald-700 rounded-t-lg p-2.5 flex items-center justify-between gap-2 flex-wrap shadow-[2px_2px_0px_#000]">
                                <div class="flex items-center gap-2.5 flex-wrap">
                                    <div class="flex items-center gap-1.5 px-2 py-0.5 rounded bg-emerald-600 text-white font-mono text-xs font-bold shadow-[1px_1px_0px_#000]">
                                        <i class="fa-solid fa-file-lines"></i>
                                        <span>NOTEPAD</span>
                                    </div>
                                    <span class="text-xs font-mono text-black/70 dark:text-emerald-300 font-semibold hidden sm:inline">Mode Teks Biasa (Tanpa Format)</span>
                                    <div class="text-[11px] font-mono text-black/60 dark:text-emerald-400/90 flex items-center gap-1.5 bg-surface px-2 py-0.5 rounded border border-black/10 dark:border-emerald-900/50">
                                        <span id="plain-text-char-count">0 karakter</span>
                                        <span>•</span>
                                        <span id="plain-text-word-count">0 kata</span>
                                        <span>•</span>
                                        <span id="plain-text-line-count">1 baris</span>
                                    </div>
                                </div>

                                <div class="flex items-center gap-1.5">
                                    <!-- Copy Entire Text Button -->
                                    <button id="plain-text-copy-btn"
                                            type="button"
                                            class="arcade-btn bg-emerald-600 hover:bg-emerald-500 active:translate-y-0.5 text-white px-2.5 sm:px-3 py-1 rounded text-xs font-mono font-bold flex items-center gap-1.5 shadow-[2px_2px_0px_#000]"
                                            title="Salin seluruh teks ke clipboard">
                                        <i class="fa-solid fa-copy"></i>
                                        <span class="copy-text-label">Copy Semua Teks</span>
                                    </button>

                                    <!-- Clear Notepad Button -->
                                    <button id="plain-text-clear-btn"
                                            type="button"
                                            class="arcade-btn bg-surface hover:bg-rose-100 dark:hover:bg-rose-950/40 text-rose-600 dark:text-rose-400 px-2.5 py-1 rounded text-xs font-mono flex items-center gap-1 shadow-[1px_1px_0px_#000]"
                                            title="Kosongkan Notepad (Clear Text)">
                                        <i class="fa-solid fa-trash-can"></i>
                                        <span class="hidden sm:inline">Bersihkan</span>
                                    </button>

                                    <!-- Switch Back to Rich Editor -->
                                    <button id="plain-text-exit-btn"
                                            type="button"
                                            class="arcade-btn bg-surface hover:bg-gray-200 dark:hover:bg-slate-800 text-black dark:text-white px-2.5 py-1 rounded text-xs font-mono flex items-center gap-1 shadow-[1px_1px_0px_#000]"
                                            title="Kembali ke Mode Rich Editor">
                                        <i class="fa-solid fa-arrow-left"></i>
                                        <span class="hidden sm:inline">Rich Editor</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Notepad Pure Plain Textarea -->
                            <textarea id="plain-text-editor"
                                      spellcheck="false"
                                      class="w-full flex-1 min-h-[400px] p-4 font-mono text-sm bg-white dark:bg-[#0c1319] text-gray-900 dark:text-emerald-300 border-2 border-t-0 border-black dark:border-emerald-700 rounded-b-lg focus:outline-none focus:ring-0 leading-relaxed resize-y selection:bg-emerald-400 selection:text-black font-mono"
                                      style="font-family: 'Fira Code', 'Courier New', Courier, monospace; font-size: 14px;"
                                      placeholder="Mulai menulis teks biasa di sini seperti di Notepad... Tidak ada format teks apa pun di dalam mode ini dan siap di-copy keseluruhan."></textarea>
                        </div>
                    </div>
                </div>



                <!-- Bottom Telemetry Bar -->
                <div class="arcade-card p-2 rounded-lg flex flex-wrap items-center justify-between gap-2 text-[11px] font-mono opacity-80 shadow-[2px_2px_0px_#000]">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span>Status: <strong class="text-emerald-700 dark:text-emerald-400 font-bold">Aktif</strong></span>
                        <span class="hidden sm:inline">| Cloud Storage: <strong class="text-blue-600 dark:text-cyan-400 font-bold">Tersinkronisasi</strong></span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span><i class="fa-solid fa-file-code text-blue-600 dark:text-cyan-400 mr-1"></i> Markdown & Rich Editor</span>
                        <span class="hidden sm:inline">| Auto-Save Aktif</span>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- ============================================================================== -->
    <!-- DATABASE CHUNK INSPECTOR & INTEGRITY AUDITOR MODAL -->
    <!-- ============================================================================== -->
    <div id="chunk-inspector-modal"
         class="fixed inset-0 z-50 bg-black/70 backdrop-blur-xs flex items-center justify-center p-3 sm:p-6 hidden">
        <div class="arcade-card w-full max-w-2xl bg-[var(--bg-surface)] rounded-xl border-4 border-black dark:border-cyan-500 shadow-[8px_8px_0px_#000] flex flex-col max-h-[85vh] overflow-hidden">

            <!-- Modal Header -->
            <div class="p-3.5 sm:p-4 border-b-2 border-black dark:border-blue-800 bg-blue-600 text-white flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-cubes text-xl"></i>
                    <div>
                        <h3 class="font-pixel text-xs sm:text-sm tracking-wider">DATABASE CHUNK TELEMETRY</h3>
                        <p class="text-[10px] font-mono text-cyan-200">Otomatis dipartisi di backend untuk performa & pencegahan overload memori</p>
                    </div>
                </div>
                <button id="chunk-modal-close" class="arcade-btn bg-black text-white hover:bg-gray-800 px-2.5 py-1 rounded text-xs font-mono">
                    <i class="fa-solid fa-xmark mr-1"></i> ESC
                </button>
            </div>

            <!-- Modal Subheader Stats & Audit Action -->
            <div class="px-4 py-2 bg-surface-alt border-b border-black/10 dark:border-white/10 flex items-center justify-between font-mono text-xs flex-wrap gap-2">
                <div class="flex items-center gap-3">
                    <span id="chunk-modal-count" class="font-bold text-blue-600 dark:text-cyan-400">Loading chunks...</span>
                    <span id="chunk-modal-bytes" class="opacity-75">0 Bytes</span>
                </div>
                <button id="verify-chunks-btn" type="button" class="arcade-btn bg-blue-600 hover:bg-blue-500 text-white px-2.5 py-1 rounded text-xs font-mono font-bold flex items-center gap-1.5 shadow-[1px_1px_0px_#000]">
                    <i class="fa-solid fa-shield-halved text-cyan-300"></i> Verify Integrity
                </button>
                <span class="text-[10px] text-emerald-600 dark:text-emerald-400 font-bold">
                    <i class="fa-solid fa-bolt mr-1"></i> Auto-Chunked Backend Performance
                </span>
            </div>

            <!-- Integrity status alert banner -->
            <div id="chunk-verify-status" class="hidden px-4 py-2 text-xs font-mono border-b border-black/10"></div>

            <!-- Modal Body (Chunks List) -->
            <div id="chunk-modal-content" class="p-4 overflow-y-auto flex-1 space-y-2">
                <!-- Dynamically loaded via app.js -->
            </div>

            <!-- Modal Footer -->
            <div class="p-3 border-t-2 border-black dark:border-blue-800 bg-surface-alt flex items-center justify-between text-[11px] font-mono">
                <span class="opacity-80">Setiap chunk memiliki identitas unik: <code class="text-cyan-600 dark:text-cyan-400">chunk_uid</code></span>
                <button onclick="window.notenux.closeChunkInspector()" class="arcade-btn bg-blue-600 text-white px-3 py-1 rounded text-xs">
                    TUTUP
                </button>
            </div>
        </div>
    </div>

    <!-- ============================================================================== -->
    <!-- IMPORT SCROLL / MARKDOWN MODAL -->
    <!-- ============================================================================== -->
    <div id="import-modal" class="fixed inset-0 z-50 bg-black/70 backdrop-blur-xs flex items-center justify-center p-3 sm:p-6 hidden">
        <div class="arcade-card w-full max-w-lg bg-[var(--bg-surface)] rounded-xl border-4 border-black dark:border-cyan-500 shadow-[8px_8px_0px_#000] flex flex-col overflow-hidden">
            <div class="p-3.5 bg-emerald-600 text-white flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-file-import text-lg"></i>
                    <h3 class="font-pixel text-xs tracking-wider">IMPORT SCROLL</h3>
                </div>
                <button id="close-import-modal-btn" class="arcade-btn bg-black text-white px-2 py-0.5 rounded text-xs font-mono">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="p-4 space-y-3 font-mono text-xs">
                <div>
                    <label class="block font-bold mb-1"><i class="fa-solid fa-file-arrow-up mr-1 text-emerald-500"></i> Upload Markdown / Text / JSON File (.md, .txt, .json)</label>
                    <input type="file" id="import-file-input" accept=".md,.markdown,.txt,.json" class="w-full p-2 bg-surface-alt border border-black/30 rounded text-xs cursor-pointer">
                </div>
                <div class="text-center font-bold text-gray-400 text-[10px]">--- OR PASTE RAW MARKDOWN CONTENT ---</div>
                <div>
                    <textarea id="import-raw-content" rows="5" placeholder="# Enter or paste your scroll content here..." class="w-full p-2 bg-surface-alt border border-black/30 rounded focus:outline-none text-xs font-mono resize-y"></textarea>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <div>
                        <label class="block font-bold mb-1">Scroll Title (Optional)</label>
                        <input type="text" id="import-title-input" placeholder="Auto-detected if empty" class="w-full p-1.5 bg-surface-alt border border-black/30 rounded text-xs focus:outline-none">
                    </div>
                    <div>
                        <label class="block font-bold mb-1">Category</label>
                        <select id="import-category-select" class="w-full p-1.5 bg-surface-alt border border-black/30 rounded text-xs focus:outline-none">
                            @if(isset($categories) && $categories->isNotEmpty())
                                @foreach($categories as $category)
                                    <option value="{{ $category->slug }}">{{ $category->name }}</option>
                                @endforeach
                            @else
                                <option value="catatan">Catatan</option>
                                <option value="tugas">Tugas</option>
                            @endif
                        </select>
                    </div>
                </div>
            </div>
            <div class="p-3 border-t-2 border-black/10 bg-surface-alt flex justify-end gap-2">
                <button id="cancel-import-btn" class="arcade-btn bg-surface px-3 py-1 rounded text-xs font-mono">Cancel</button>
                <button id="confirm-import-btn" class="arcade-btn bg-emerald-600 hover:bg-emerald-500 text-white px-3 py-1 rounded text-xs font-mono">
                    <i class="fa-solid fa-file-import mr-1"></i> IMPORT NASKAH
                </button>
            </div>
        </div>
    </div>

    <!-- ============================================================================== -->
    <!-- ARCADE KEYBOARD SHORTCUTS GUIDE MODAL -->
    <!-- ============================================================================== -->
    <div id="shortcuts-modal" class="fixed inset-0 z-50 bg-black/70 backdrop-blur-xs flex items-center justify-center p-3 sm:p-6 hidden">
        <div class="arcade-card w-full max-w-xl bg-[var(--bg-surface)] rounded-xl border-4 border-black dark:border-cyan-500 shadow-[8px_8px_0px_#000] flex flex-col overflow-hidden">
            <div class="p-3.5 bg-blue-600 text-white flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-gamepad text-lg text-yellow-400"></i>
                    <h3 class="font-pixel text-xs tracking-wider">ARCADE SHORTCUT CHEAT SHEET</h3>
                </div>
                <button id="close-shortcuts-modal-btn" class="arcade-btn bg-black text-white px-2 py-0.5 rounded text-xs font-mono">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="p-4 space-y-2.5 font-mono text-xs overflow-y-auto max-h-[70vh]">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <div class="p-2 rounded bg-surface-alt border border-black/20 flex items-center justify-between">
                        <span>Save Active Scroll</span>
                        <kbd class="px-1.5 py-0.5 rounded bg-black text-cyan-300 border border-cyan-500 text-[10px]">Ctrl + S</kbd>
                    </div>
                    <div class="p-2 rounded bg-surface-alt border border-black/20 flex items-center justify-between">
                        <span>New Scroll</span>
                        <kbd class="px-1.5 py-0.5 rounded bg-black text-cyan-300 border border-cyan-500 text-[10px]">Ctrl + N</kbd>
                    </div>
                    <div class="p-2 rounded bg-surface-alt border border-black/20 flex items-center justify-between">
                        <span>Find & Replace</span>
                        <kbd class="px-1.5 py-0.5 rounded bg-black text-cyan-300 border border-cyan-500 text-[10px]">Ctrl + F</kbd>
                    </div>
                    <div class="p-2 rounded bg-surface-alt border border-black/20 flex items-center justify-between">
                        <span>Duplicate Scroll</span>
                        <kbd class="px-1.5 py-0.5 rounded bg-black text-cyan-300 border border-cyan-500 text-[10px]">Ctrl + D</kbd>
                    </div>
                    <div class="p-2 rounded bg-surface-alt border border-black/20 flex items-center justify-between">
                        <span>Toggle RAW MD Mode</span>
                        <kbd class="px-1.5 py-0.5 rounded bg-black text-cyan-300 border border-cyan-500 text-[10px]">Ctrl + M</kbd>
                    </div>
                    <div class="p-2 rounded bg-surface-alt border border-black/20 flex items-center justify-between">
                        <span>Mode Notepad (Teks Biasa)</span>
                        <kbd class="px-1.5 py-0.5 rounded bg-black text-emerald-400 border border-emerald-500 text-[10px]">Alt + N</kbd>
                    </div>
                    <div class="p-2 rounded bg-surface-alt border border-black/20 flex items-center justify-between">
                        <span>Export Scroll</span>
                        <kbd class="px-1.5 py-0.5 rounded bg-black text-cyan-300 border border-cyan-500 text-[10px]">Ctrl + E</kbd>
                    </div>
                    <div class="p-2 rounded bg-surface-alt border border-black/20 flex items-center justify-between">
                        <span>Zen Focus Mode</span>
                        <kbd class="px-1.5 py-0.5 rounded bg-black text-cyan-300 border border-cyan-500 text-[10px]">Alt + Z</kbd>
                    </div>
                    <div class="p-2 rounded bg-surface-alt border border-black/20 flex items-center justify-between">
                        <span>Tebal (Bold)</span>
                        <kbd class="px-1.5 py-0.5 rounded bg-black text-cyan-300 border border-cyan-500 text-[10px]">Ctrl + B</kbd>
                    </div>
                    <div class="p-2 rounded bg-surface-alt border border-black/20 flex items-center justify-between">
                        <span>Batal (Undo)</span>
                        <kbd class="px-1.5 py-0.5 rounded bg-black text-cyan-300 border border-cyan-500 text-[10px]">Ctrl + Z</kbd>
                    </div>
                    <div class="p-2 rounded bg-surface-alt border border-black/20 flex items-center justify-between">
                        <span>Ulangi (Redo)</span>
                        <kbd class="px-1.5 py-0.5 rounded bg-black text-cyan-300 border border-cyan-500 text-[10px]">Ctrl + Y / Ctrl + Shift + Z</kbd>
                    </div>
                    <div class="p-2 rounded bg-surface-alt border border-black/20 flex items-center justify-between">
                        <span>Cheat Sheet (This)</span>
                        <kbd class="px-1.5 py-0.5 rounded bg-black text-cyan-300 border border-cyan-500 text-[10px]">? or F1</kbd>
                    </div>
                </div>
            </div>
            <div class="p-3 border-t-2 border-black/10 bg-surface-alt flex justify-end">
                <button onclick="document.getElementById('shortcuts-modal').classList.add('hidden')" class="arcade-btn bg-blue-600 text-white px-3 py-1 rounded text-xs font-mono">
                    READY TO QUEST
                </button>
            </div>
        </div>
    </div>

    <!-- ============================================================================== -->
    <!-- INTERACTIVE ARCADE DELETE CONFIRMATION MODAL (NO DEFAULT ALERT/CONFIRM) -->
    <!-- ============================================================================== -->
    <div id="delete-confirm-modal" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-xs flex items-center justify-center p-4 hidden">
        <div class="arcade-card w-full max-w-md bg-[var(--bg-surface)] rounded-2xl border-4 border-rose-600 shadow-[8px_8px_0px_#000] flex flex-col overflow-hidden animate-pop-in">
            <!-- Modal Warning Header -->
            <div class="p-3.5 sm:p-4 bg-rose-600 text-white flex items-center justify-between border-b-2 border-black">
                <div class="flex items-center gap-2 font-mono font-bold">
                    <i class="fa-solid fa-triangle-exclamation text-lg text-yellow-300 animate-pulse"></i>
                    <h3 class="font-pixel text-xs tracking-wider">BANISH SCROLL CONFIRMATION</h3>
                </div>
                <button id="close-delete-modal-x" class="arcade-btn bg-black text-white hover:bg-gray-800 px-2 py-0.5 rounded text-xs font-mono" title="Abort (ESC)">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <!-- Modal Content Body -->
            <div class="p-5 space-y-4 font-mono text-xs">
                <div class="text-center py-1">
                    <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-rose-100 dark:bg-rose-950/70 border-2 border-rose-500 text-rose-600 dark:text-rose-400 mb-3 shadow-[2px_2px_0px_#000]">
                        <i class="fa-solid fa-skull-crossbones text-2xl"></i>
                    </div>
                    <h4 class="text-sm font-bold text-rose-600 dark:text-rose-400 mb-1">Banish Scroll to Digital Void?</h4>
                    <p class="text-xs opacity-80 max-w-xs mx-auto">
                        Tindakan ini tidak dapat dibatalkan. Catatan ini beserta seluruh isinya akan dihapus secara permanen dari penyimpanan.
                    </p>
                </div>

                <!-- Scroll Target Badge -->
                <div class="p-3 bg-rose-50 dark:bg-rose-950/40 border-2 border-rose-300 dark:border-rose-900 rounded-xl">
                    <div class="text-[10px] uppercase font-bold text-rose-500 tracking-wider mb-1 flex items-center gap-1.5">
                        <i class="fa-solid fa-scroll"></i> TARGET SCROLL:
                    </div>
                    <div id="delete-modal-scroll-title" class="font-bold text-sm text-rose-800 dark:text-rose-200 truncate">
                        Scroll Title
                    </div>
                </div>

                <!-- Database Info Pill -->
                <div class="p-2.5 bg-amber-50 dark:bg-amber-950/40 border border-amber-300 dark:border-amber-800 rounded-lg text-[11px] text-amber-800 dark:text-amber-200 flex items-center gap-2">
                    <i class="fa-solid fa-shield-halved text-amber-500 shrink-0"></i>
                    <span>Seluruh data catatan akan dibersihkan secara aman dari database.</span>
                </div>
            </div>

            <!-- Modal Actions Footer -->
            <div class="p-3.5 border-t-2 border-black/10 dark:border-white/10 bg-surface-alt flex items-center justify-end gap-2.5">
                <button id="cancel-delete-modal-btn" class="arcade-btn bg-surface hover:bg-gray-200 dark:hover:bg-slate-800 px-4 py-2 rounded-lg text-xs font-mono font-bold flex items-center gap-1.5 shadow-[2px_2px_0px_#000]">
                    <kbd class="px-1 py-0.5 rounded bg-black/10 dark:bg-white/10 text-[10px]">ESC</kbd>
                    <span>ABORT</span>
                </button>
                <button id="confirm-delete-modal-btn" class="arcade-btn bg-rose-600 hover:bg-rose-500 text-white px-4 py-2 rounded-lg text-xs font-mono font-bold tracking-wider flex items-center gap-2 shadow-[2px_2px_0px_#000] active:translate-y-0.5">
                    <i class="fa-solid fa-fire"></i>
                    <span>YES, BANISH SCROLL</span>
                </button>
            </div>
        </div>
    </div>

    <!-- ============================================================================== -->
    <!-- BULK DELETE MULTIPLE SCROLLS CONFIRMATION MODAL -->
    <!-- ============================================================================== -->
    <div id="bulk-delete-notes-modal" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-xs flex items-center justify-center p-4 hidden">
        <div class="arcade-card w-full max-w-md bg-[var(--bg-surface)] rounded-2xl border-4 border-rose-600 shadow-[8px_8px_0px_#000] flex flex-col overflow-hidden animate-pop-in">
            <!-- Modal Warning Header -->
            <div class="p-3.5 sm:p-4 bg-rose-600 text-white flex items-center justify-between border-b-2 border-black">
                <div class="flex items-center gap-2 font-mono font-bold">
                    <i class="fa-solid fa-triangle-exclamation text-lg text-yellow-300 animate-pulse"></i>
                    <h3 class="font-pixel text-xs tracking-wider">BANISH MULTIPLE SCROLLS</h3>
                </div>
                <button id="close-bulk-delete-notes-x" class="arcade-btn bg-black text-white hover:bg-gray-800 px-2 py-0.5 rounded text-xs font-mono" title="Abort (ESC)">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <!-- Modal Content Body -->
            <div class="p-5 space-y-4 font-mono text-xs">
                <div class="text-center py-1">
                    <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-rose-100 dark:bg-rose-950/70 border-2 border-rose-500 text-rose-600 dark:text-rose-400 mb-3 shadow-[2px_2px_0px_#000]">
                        <i class="fa-solid fa-dumpster-fire text-2xl"></i>
                    </div>
                    <h4 class="text-sm font-bold text-rose-600 dark:text-rose-400 mb-1">Banish Selected Scrolls to Void?</h4>
                    <p class="text-xs opacity-80 max-w-xs mx-auto">
                        Tindakan ini permanen. Semua gulungan catatan terpilih beserta seluruh data fragmen/chunk akan dibersihkan dari database.
                    </p>
                </div>

                <!-- Selected Count Badge -->
                <div class="p-3 bg-rose-50 dark:bg-rose-950/40 border-2 border-rose-300 dark:border-rose-900 rounded-xl">
                    <div class="text-[10px] uppercase font-bold text-rose-500 tracking-wider mb-1 flex items-center gap-1.5">
                        <i class="fa-solid fa-scroll"></i> TOTAL TARGET:
                    </div>
                    <div id="bulk-delete-notes-count-badge" class="font-bold text-sm text-rose-800 dark:text-rose-200">
                        0 Gulungan Terpilih
                    </div>
                </div>

                <div class="p-2.5 bg-amber-50 dark:bg-amber-950/40 border border-amber-300 dark:border-amber-800 rounded-lg text-[11px] text-amber-800 dark:text-amber-200 flex items-center gap-2">
                    <i class="fa-solid fa-shield-halved text-amber-500 shrink-0"></i>
                    <span>Tindakan multiple delete bersifat atomik dan aman dari fragmentasi data.</span>
                </div>
            </div>

            <!-- Modal Actions Footer -->
            <div class="p-3.5 border-t-2 border-black/10 dark:border-white/10 bg-surface-alt flex items-center justify-end gap-2.5">
                <button id="cancel-bulk-delete-notes-btn" class="arcade-btn bg-surface hover:bg-gray-200 dark:hover:bg-slate-800 px-4 py-2 rounded-lg text-xs font-mono font-bold flex items-center gap-1.5 shadow-[2px_2px_0px_#000]">
                    <span>BATAL</span>
                </button>
                <button id="confirm-bulk-delete-notes-btn" class="arcade-btn bg-rose-600 hover:bg-rose-500 text-white px-4 py-2 rounded-lg text-xs font-mono font-bold tracking-wider flex items-center gap-2 shadow-[2px_2px_0px_#000] active:translate-y-0.5">
                    <i class="fa-solid fa-fire"></i>
                    <span>BANISH SELECTED</span>
                </button>
            </div>
        </div>
    </div>

    <!-- ============================================================================== -->
    <!-- QUICK EDIT CATEGORY MODAL -->
    <!-- ============================================================================== -->
    <div id="quick-edit-category-modal" class="fixed inset-0 z-50 bg-black/75 backdrop-blur-xs flex items-center justify-center p-3 sm:p-6 hidden">
        <div class="arcade-card w-full max-w-md bg-[var(--bg-surface)] rounded-xl border-4 border-cyan-500 shadow-[8px_8px_0px_#000] flex flex-col overflow-hidden">
            <div class="p-3.5 bg-blue-600 text-white flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-pen-to-square text-lg text-cyan-300"></i>
                    <h3 class="font-pixel text-xs tracking-wider">EDIT CATEGORY</h3>
                </div>
                <button type="button" id="close-quick-edit-cat-btn" class="arcade-btn bg-black text-white px-2 py-0.5 rounded text-xs font-mono">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form id="quick-edit-category-form" class="p-4 space-y-3 font-mono text-xs">
                <input type="hidden" id="quick-edit-cat-id">
                <div>
                    <label class="block font-bold mb-1">Category Name <span class="text-rose-500">*</span></label>
                    <input type="text" id="quick-edit-cat-name" required class="w-full p-2 bg-surface-alt border border-black/30 rounded text-xs focus:outline-none focus:border-cyan-400">
                </div>

                <div>
                    <label class="block font-bold mb-1">Slug / Identifier</label>
                    <input type="text" id="quick-edit-cat-slug" placeholder="Auto-maintained if unchanged" class="w-full p-2 bg-surface-alt border border-black/30 rounded text-xs focus:outline-none focus:border-cyan-400">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <div>
                        <label class="block font-bold mb-1">Color Palette</label>
                        <select id="quick-edit-cat-color" class="w-full p-2 bg-surface-alt border border-black/30 rounded text-xs focus:outline-none">
                            <option value="cyan">Neon Cyan</option>
                            <option value="blue">Arcade Blue</option>
                            <option value="emerald">Emerald Glow</option>
                            <option value="amber">Pixel Gold</option>
                            <option value="rose">Crimson Red</option>
                            <option value="purple">Arcane Purple</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold mb-1">Icon (Font Awesome)</label>
                        <input type="text" id="quick-edit-cat-icon" placeholder="fa-solid fa-..." class="w-full p-2 bg-surface-alt border border-black/30 rounded text-xs focus:outline-none">
                    </div>
                </div>

                <div>
                    <label class="block font-bold mb-1">Description (Optional)</label>
                    <textarea id="quick-edit-cat-desc" rows="2" placeholder="Brief category description..." class="w-full p-2 bg-surface-alt border border-black/30 rounded text-xs focus:outline-none resize-y"></textarea>
                </div>

                <div class="pt-2 flex justify-end gap-2 border-t border-black/10">
                    <button type="button" id="cancel-quick-edit-cat-btn" class="arcade-btn bg-surface px-3 py-1.5 rounded text-xs">Cancel</button>
                    <button type="submit" class="arcade-btn bg-cyan-600 hover:bg-cyan-500 text-white px-4 py-1.5 rounded text-xs font-bold">
                        <i class="fa-solid fa-check mr-1"></i> SAVE CHANGES
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ============================================================================== -->
    <!-- QUICK DELETE CATEGORY CONFIRMATION MODAL -->
    <!-- ============================================================================== -->
    <div id="quick-delete-category-modal" class="fixed inset-0 z-50 bg-black/75 backdrop-blur-xs flex items-center justify-center p-3 sm:p-6 hidden">
        <div class="arcade-card w-full max-w-md bg-[var(--bg-surface)] rounded-xl border-4 border-rose-600 shadow-[8px_8px_0px_#000] flex flex-col overflow-hidden">
            <div class="p-3.5 bg-rose-600 text-white flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-triangle-exclamation text-lg text-yellow-300"></i>
                    <h3 class="font-pixel text-xs tracking-wider">BANISH CATEGORY</h3>
                </div>
                <button type="button" id="close-quick-delete-cat-btn" class="arcade-btn bg-black text-white px-2 py-0.5 rounded text-xs font-mono">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="p-4 space-y-3 font-mono text-xs">
                <input type="hidden" id="quick-delete-cat-id">
                <p>Are you sure you want to dismantle category <span id="quick-delete-cat-name-badge" class="font-bold text-rose-500"></span>?</p>
                <div class="p-2.5 bg-rose-100 dark:bg-rose-950/40 border border-rose-300 dark:border-rose-800 rounded text-rose-900 dark:text-rose-200 text-[11px]">
                    <i class="fa-solid fa-triangle-exclamation text-rose-500 mr-1"></i>
                    <strong>Peringatan / Warning:</strong> Semua scroll dan catatan yang terhubung dengan kategori ini akan otomatis ikut terhapus secara permanen.
                </div>
            </div>

            <div class="p-3 border-t border-black/10 bg-surface-alt flex justify-end gap-2 font-mono text-xs">
                <button type="button" id="cancel-quick-delete-cat-btn" class="arcade-btn bg-surface px-3 py-1.5 rounded">Cancel</button>
                <button type="button" id="confirm-quick-delete-cat-btn" class="arcade-btn bg-rose-600 hover:bg-rose-500 text-white px-4 py-1.5 rounded font-bold">
                    <i class="fa-solid fa-trash mr-1"></i> YES, BANISH CATEGORY
                </button>
            </div>
        </div>
    </div>

    <!-- ============================================================================== -->
    <!-- QUICK FORGE CATEGORY MODAL -->
    <!-- ============================================================================== -->
    <div id="quick-forge-category-modal" class="fixed inset-0 z-50 bg-black/75 backdrop-blur-xs flex items-center justify-center p-3 sm:p-6 hidden">
        <div class="arcade-card w-full max-w-md bg-[var(--bg-surface)] rounded-xl border-4 border-emerald-500 shadow-[8px_8px_0px_#000] flex flex-col overflow-hidden">
            <div class="p-3.5 bg-emerald-600 text-white flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-wand-sparkles text-lg text-emerald-200"></i>
                    <h3 class="font-pixel text-xs tracking-wider">FORGE NEW CATEGORY</h3>
                </div>
                <button type="button" id="close-quick-forge-cat-btn" class="arcade-btn bg-black text-white px-2 py-0.5 rounded text-xs font-mono">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form id="quick-forge-category-form" class="p-4 space-y-3 font-mono text-xs">
                <div>
                    <label class="block font-bold mb-1">Category Name <span class="text-rose-500">*</span></label>
                    <input type="text" id="quick-forge-cat-name" required placeholder="e.g. Alchemy, Boss Tactics" class="w-full p-2 bg-surface-alt border border-black/30 rounded text-xs focus:outline-none focus:border-emerald-400">
                </div>

                <div>
                    <label class="block font-bold mb-1">Slug / Identifier (Optional)</label>
                    <input type="text" id="quick-forge-cat-slug" placeholder="Auto-generated if empty" class="w-full p-2 bg-surface-alt border border-black/30 rounded text-xs focus:outline-none focus:border-emerald-400">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <div>
                        <label class="block font-bold mb-1">Color Palette</label>
                        <select id="quick-forge-cat-color" class="w-full p-2 bg-surface-alt border border-black/30 rounded text-xs focus:outline-none">
                            <option value="cyan">Neon Cyan</option>
                            <option value="blue">Arcade Blue</option>
                            <option value="emerald" selected>Emerald Glow</option>
                            <option value="amber">Pixel Gold</option>
                            <option value="rose">Crimson Red</option>
                            <option value="purple">Arcane Purple</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold mb-1">Icon (Font Awesome)</label>
                        <input type="text" id="quick-forge-cat-icon" value="fa-solid fa-folder" placeholder="fa-solid fa-folder" class="w-full p-2 bg-surface-alt border border-black/30 rounded text-xs focus:outline-none">
                    </div>
                </div>

                <div>
                    <label class="block font-bold mb-1">Description (Optional)</label>
                    <textarea id="quick-forge-cat-desc" rows="2" placeholder="Brief description of this category..." class="w-full p-2 bg-surface-alt border border-black/30 rounded text-xs focus:outline-none resize-y"></textarea>
                </div>

                <div class="pt-2 flex justify-end gap-2 border-t border-black/10">
                    <button type="button" id="cancel-quick-forge-cat-btn" class="arcade-btn bg-surface px-3 py-1.5 rounded text-xs">Cancel</button>
                    <button type="submit" class="arcade-btn bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-1.5 rounded text-xs font-bold">
                        <i class="fa-solid fa-hammer mr-1"></i> FORGE CATEGORY
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ============================================================================== -->
    <!-- MARKDOWN EDITOR & LIVE PREVIEW MODAL -->
    <!-- ============================================================================== -->
    <div id="markdown-modal" class="fixed inset-0 z-50 bg-black/75 backdrop-blur-xs flex items-center justify-center p-3 sm:p-6 hidden">
        <div class="arcade-card w-full max-w-4xl bg-[var(--bg-surface)] rounded-xl border-4 border-black dark:border-cyan-500 shadow-[8px_8px_0px_#000] flex flex-col max-h-[90vh] overflow-hidden">
            <!-- Modal Header -->
            <div class="p-3.5 bg-cyan-600 text-white flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-file-code text-lg"></i>
                    <h3 class="font-pixel text-xs tracking-wider">MARKDOWN EDITOR & LIVE PREVIEW</h3>
                </div>
                <button type="button" id="close-markdown-modal-btn" class="arcade-btn bg-black text-white px-2 py-0.5 rounded text-xs font-mono">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <!-- View Toggles & Status -->
            <div class="p-3 bg-[var(--bg-surface-alt)] border-b-2 border-black/10 dark:border-white/10 flex items-center justify-between flex-wrap gap-2 text-xs font-mono">
                <div class="flex items-center gap-1.5">
                    <button type="button" id="md-tab-split-btn" class="arcade-btn bg-cyan-600 text-white px-2.5 py-1 rounded text-xs font-bold">
                        <i class="fa-solid fa-table-columns mr-1"></i> Split View
                    </button>
                    <button type="button" id="md-tab-editor-btn" class="arcade-btn bg-[var(--bg-surface)] px-2.5 py-1 rounded text-xs">
                        <i class="fa-solid fa-pen-to-square mr-1"></i> Editor Saja
                    </button>
                    <button type="button" id="md-tab-preview-btn" class="arcade-btn bg-[var(--bg-surface)] px-2.5 py-1 rounded text-xs">
                        <i class="fa-solid fa-eye mr-1"></i> Preview Saja
                    </button>
                </div>

                <div class="flex items-center gap-3 text-[11px] text-slate-600 dark:text-slate-300">
                    <span><strong id="md-modal-word-count">0</strong> kata</span>
                    <span>&bull;</span>
                    <span><strong id="md-modal-char-count">0</strong> karakter</span>
                </div>
            </div>

            <!-- Modal Body: Split / Tabbed View -->
            <div class="p-4 overflow-y-auto flex-1 grid grid-cols-1 md:grid-cols-2 gap-4 text-xs font-mono" id="md-modal-grid">
                <!-- Editor Pane -->
                <div id="md-modal-editor-pane" class="flex flex-col space-y-1.5">
                    <div class="flex items-center justify-between font-bold text-blue-900 dark:text-cyan-300">
                        <label for="markdown-modal-textarea" class="flex items-center gap-1">
                            <i class="fa-brands fa-markdown text-base text-cyan-500"></i> Markdown Editor
                        </label>
                        <button type="button" id="md-insert-sample-btn" class="text-[10px] text-cyan-600 dark:text-cyan-400 underline hover:opacity-80">
                            + Masukkan Contoh
                        </button>
                    </div>
                    <textarea id="markdown-modal-textarea"
                              rows="12"
                              placeholder="# Judul Dokumen&#10;&#10;Tulis atau tempel teks Markdown (.md) Anda di sini...&#10;&#10;**Tebal**, *Miring*, dan `Kode inline`&#10;&#10;- [x] Tugas selesai&#10;- [ ] Rencana berikutnya&#10;&#10;| Kolom 1 | Kolom 2 |&#10;| --- | --- |&#10;| Data A | Data B |"
                              class="w-full flex-1 p-3 bg-[var(--bg-main)] text-[var(--text-main)] border-2 border-black/30 dark:border-blue-700/60 rounded-lg focus:outline-none focus:border-cyan-500 text-xs font-mono leading-relaxed resize-y"></textarea>
                </div>

                <!-- Preview Pane -->
                <div id="md-modal-preview-pane" class="flex flex-col space-y-1.5">
                    <div class="flex items-center justify-between font-bold text-blue-900 dark:text-cyan-300">
                        <span class="flex items-center gap-1">
                            <i class="fa-solid fa-eye text-base text-purple-500"></i> Live Preview HTML
                        </span>
                        <span class="text-[10px] px-1.5 py-0.5 rounded bg-purple-100 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300 border border-purple-500/30">
                            Render Instan
                        </span>
                    </div>
                    <div id="markdown-modal-preview"
                         class="w-full flex-1 p-3 bg-[var(--bg-main)] text-[var(--text-main)] border-2 border-dashed border-purple-500/40 rounded-lg overflow-y-auto max-w-none text-xs leading-relaxed min-h-[220px]">
                        <p class="text-slate-400 italic">Preview hasil terjemahan Markdown akan otomatis muncul di sini...</p>
                    </div>
                </div>
            </div>

            <!-- Footer Action Controls -->
            <div class="p-3.5 border-t-2 border-black/10 dark:border-white/10 bg-[var(--bg-surface-alt)] flex flex-wrap items-center justify-between gap-3 font-mono text-xs">
                <div class="flex items-center gap-3">
                    <label class="flex items-center gap-1.5 cursor-pointer">
                        <input type="radio" name="md_apply_mode" value="cursor" checked class="text-cyan-600 focus:ring-0">
                        <span>Sisipkan di Kursor</span>
                    </label>
                    <label class="flex items-center gap-1.5 cursor-pointer">
                        <input type="radio" name="md_apply_mode" value="replace" class="text-cyan-600 focus:ring-0">
                        <span>Ganti Seluruh Catatan</span>
                    </label>
                </div>

                <div class="flex items-center gap-2">
                    <button type="button" id="cancel-markdown-modal-btn" class="arcade-btn bg-[var(--bg-surface)] px-3 py-1.5 rounded text-xs font-mono">
                        Batal
                    </button>
                    <button type="button" id="confirm-markdown-modal-btn" class="arcade-btn bg-cyan-600 hover:bg-cyan-500 text-white px-4 py-1.5 rounded text-xs font-mono font-bold">
                        <i class="fa-solid fa-check mr-1.5"></i> Terapkan ke Catatan
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================================== -->
    <!-- CLEAR CONFIRMATION MODAL (POPUP PILIHAN BERSIHKAN) -->
    <!-- ============================================================================== -->
    <div id="clear-confirm-modal" class="fixed inset-0 z-50 bg-black/75 backdrop-blur-xs flex items-center justify-center p-3 sm:p-6 hidden">
        <div class="arcade-card w-full max-w-md bg-[var(--bg-surface)] rounded-xl border-4 border-black dark:border-rose-600 shadow-[8px_8px_0px_#000] flex flex-col overflow-hidden">
            <div class="p-3.5 bg-rose-600 text-white flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-broom text-lg"></i>
                    <h3 class="font-pixel text-xs tracking-wider">BERSIHKAN EDITOR</h3>
                </div>
                <button type="button" id="close-clear-modal-x" class="arcade-btn bg-black text-white px-2 py-0.5 rounded text-xs font-mono">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="p-5 space-y-4 font-mono text-xs">
                <p class="text-[var(--text-main)] leading-relaxed font-bold">
                    Pilih tindakan pembersihan untuk editor catatan aktif:
                </p>
                <div class="space-y-3">
                    <button type="button" id="clear-formatting-only-btn" class="arcade-btn w-full p-3 rounded-lg bg-amber-100 hover:bg-amber-200 dark:bg-amber-950/60 dark:hover:bg-amber-900/80 text-amber-950 dark:text-amber-200 border-2 border-amber-600 text-left flex items-start gap-2.5">
                        <i class="fa-solid fa-wand-magic-sparkles text-base mt-0.5 text-amber-600 shrink-0"></i>
                        <div>
                            <div class="font-bold">1. Bersihkan Format Teks Saja</div>
                            <div class="text-[11px] opacity-80 mt-0.5">Menghapus semua warna, ukuran, tebal/miring, dan tag heading menjadi teks polos tanpa menghapus isi tulisanmu.</div>
                        </div>
                    </button>

                    <button type="button" id="clear-all-content-btn" class="arcade-btn w-full p-3 rounded-lg bg-rose-100 hover:bg-rose-200 dark:bg-rose-950/60 dark:hover:bg-rose-900/80 text-rose-950 dark:text-rose-200 border-2 border-rose-600 text-left flex items-start gap-2.5">
                        <i class="fa-solid fa-trash-can text-base mt-0.5 text-rose-600 shrink-0"></i>
                        <div>
                            <div class="font-bold">2. Kosongkan Seluruh Isi Catatan</div>
                            <div class="text-[11px] opacity-80 mt-0.5">Mengosongkan seluruh lembar kerja di editor aktif.</div>
                        </div>
                    </button>
                </div>
            </div>
            <div class="p-3 border-t-2 border-black/10 dark:border-white/10 bg-[var(--bg-surface-alt)] flex justify-end">
                <button type="button" id="cancel-clear-modal-btn" class="arcade-btn bg-[var(--bg-surface)] px-4 py-1.5 rounded text-xs font-mono font-bold">
                    Batal
                </button>
            </div>
        </div>
    </div>

    <!-- ============================================================================== -->
    <!-- HERO PROFILE & SECURITY SETTINGS MODAL -->
    <!-- ============================================================================== -->
    <div id="profile-settings-modal" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4 hidden">
        <div class="arcade-card bg-[var(--bg-surface)] border-2 border-black dark:border-blue-700 w-full max-w-lg rounded-xl overflow-hidden shadow-[6px_6px_0px_#000] flex flex-col max-h-[90vh]">
            <!-- Modal Header -->
            <div class="p-3.5 bg-blue-600 dark:bg-blue-900 border-b-2 border-black text-white flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-user-shield text-base text-yellow-300"></i>
                    <h2 class="font-pixel text-xs sm:text-sm tracking-wider uppercase">HERO GUILD IDENTITY & SECURITY</h2>
                </div>
                <button type="button" id="close-profile-modal-x" class="arcade-btn bg-black text-white hover:bg-gray-800 px-2 py-0.5 rounded text-xs font-mono" title="Tutup (ESC)">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="p-4 sm:p-5 overflow-y-auto space-y-4 text-xs font-mono">
                <!-- Hero Gamification HUD Card -->
                <div class="arcade-card p-3 rounded-lg border-2 border-blue-500 bg-blue-50 dark:bg-blue-950/40 shadow-[2px_2px_0px_#000] flex items-center gap-3">
                    <div class="w-12 h-12 rounded border-2 border-black bg-blue-600 flex items-center justify-center text-white shrink-0 shadow-[2px_2px_0px_#000]">
                        <i class="fa-solid fa-hat-wizard text-xl"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between">
                            <span class="font-pixel text-xs text-blue-900 dark:text-cyan-300 font-bold" id="profile-modal-rank-title">{{ $stats['rank_title'] ?? 'Novice Scribe' }}</span>
                            <span class="font-pixel text-[10px] bg-blue-600 text-white px-1.5 py-0.5 rounded border border-black" id="profile-modal-level">LV.{{ $stats['level'] ?? 1 }}</span>
                        </div>
                        <div class="flex items-center justify-between mt-1 text-[11px] text-slate-700 dark:text-gray-300">
                            <span><i class="fa-solid fa-scroll mr-1 text-blue-600 dark:text-cyan-400"></i>{{ $stats['total_notes'] ?? 0 }} Catatan</span>
                            <span><i class="fa-solid fa-feather-pointed mr-1 text-purple-600 dark:text-purple-400"></i>{{ $stats['total_words'] ?? 0 }} Kata</span>
                            <span class="text-amber-600 dark:text-yellow-400 font-bold">{{ $stats['total_xp'] ?? 10 }} XP</span>
                        </div>
                    </div>
                </div>

                <!-- Profile Form -->
                <form id="profile-settings-form" class="space-y-3.5">
                    <!-- Name Field -->
                    <div>
                        <label for="profile-name-input" class="block font-bold mb-1 text-slate-800 dark:text-gray-200">
                            <i class="fa-solid fa-user mr-1 text-blue-600 dark:text-cyan-400"></i> Nama Adventurer:
                        </label>
                        <input type="text"
                               id="profile-name-input"
                               name="name"
                               value="{{ $user->name ?? '' }}"
                               required
                               maxlength="255"
                               class="retro-input w-full p-2.5 rounded bg-[var(--bg-main)] border-2 border-black dark:border-blue-700 font-mono text-xs focus:ring-2 focus:ring-blue-500">
                    </div>

                    <!-- Email Field -->
                    <div>
                        <label for="profile-email-input" class="block font-bold mb-1 text-slate-800 dark:text-gray-200">
                            <i class="fa-solid fa-envelope mr-1 text-blue-600 dark:text-cyan-400"></i> Email Arcane:
                        </label>
                        <input type="email"
                               id="profile-email-input"
                               name="email"
                               value="{{ $user->email ?? '' }}"
                               required
                               maxlength="255"
                               class="retro-input w-full p-2.5 rounded bg-[var(--bg-main)] border-2 border-black dark:border-blue-700 font-mono text-xs focus:ring-2 focus:ring-blue-500">
                    </div>

                    <!-- Password Section Divider -->
                    <div class="pt-2 border-t-2 border-dashed border-black/20 dark:border-blue-900/40">
                        <span class="font-bold text-[11px] text-blue-700 dark:text-cyan-400 uppercase tracking-wider flex items-center gap-1.5">
                            <i class="fa-solid fa-key"></i> Ubah Password Kredensial (Opsional)
                        </span>
                        <p class="text-[10px] opacity-70 mt-0.5">Biarkan kosong jika tidak ingin mengubah password akun.</p>
                    </div>

                    <!-- Current Password -->
                    <div>
                        <label for="profile-current-password-input" class="block font-bold mb-1 text-slate-800 dark:text-gray-200">
                            Password Saat Ini:
                        </label>
                        <div class="relative">
                            <input type="password"
                                   id="profile-current-password-input"
                                   name="current_password"
                                   placeholder="Masukkan password saat ini..."
                                   class="retro-input w-full p-2.5 pr-9 rounded bg-[var(--bg-main)] border-2 border-black dark:border-blue-700 font-mono text-xs">
                            <button type="button" class="toggle-password-btn password-toggle-btn absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 cursor-pointer" data-target="profile-current-password-input" title="Tampilkan kata sandi">
                                <i class="fa-solid fa-eye text-xs"></i>
                            </button>
                        </div>
                    </div>

                    <!-- New Password & Confirmation -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label for="profile-new-password-input" class="block font-bold mb-1 text-slate-800 dark:text-gray-200">
                                Password Baru:
                            </label>
                            <div class="relative">
                                <input type="password"
                                       id="profile-new-password-input"
                                       name="password"
                                       placeholder="Minimal 8 karakter..."
                                       class="retro-input w-full p-2.5 pr-9 rounded bg-[var(--bg-main)] border-2 border-black dark:border-blue-700 font-mono text-xs">
                                <button type="button" class="toggle-password-btn password-toggle-btn absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 cursor-pointer" data-target="profile-new-password-input" title="Tampilkan kata sandi">
                                    <i class="fa-solid fa-eye text-xs"></i>
                                </button>
                            </div>
                        </div>

                        <div>
                            <label for="profile-confirm-password-input" class="block font-bold mb-1 text-slate-800 dark:text-gray-200">
                                Konfirmasi Password:
                            </label>
                            <div class="relative">
                                <input type="password"
                                       id="profile-confirm-password-input"
                                       name="password_confirmation"
                                       placeholder="Ulangi password..."
                                       class="retro-input w-full p-2.5 pr-9 rounded bg-[var(--bg-main)] border-2 border-black dark:border-blue-700 font-mono text-xs">
                                <button type="button" class="toggle-password-btn password-toggle-btn absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 cursor-pointer" data-target="profile-confirm-password-input" title="Tampilkan konfirmasi kata sandi">
                                    <i class="fa-solid fa-eye text-xs"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Error feedback element -->
                    <div id="profile-form-error" class="hidden p-2.5 rounded bg-rose-100 dark:bg-rose-950/60 text-rose-800 dark:text-rose-200 border-2 border-rose-500 text-[11px]"></div>
                </form>
            </div>

            <!-- Modal Footer -->
            <div class="p-3 border-t-2 border-black/10 dark:border-white/10 bg-[var(--bg-surface-alt)] flex items-center justify-between gap-2">
                <button type="button" id="close-profile-modal-btn" class="arcade-btn bg-[var(--bg-surface)] hover:bg-gray-200 dark:hover:bg-slate-800 px-4 py-2 rounded text-xs font-mono font-bold shadow-[2px_2px_0px_#000]">
                    Batal
                </button>
                <button type="button" id="save-profile-btn" class="arcade-btn bg-blue-600 hover:bg-blue-500 text-white px-5 py-2 rounded text-xs font-mono font-bold tracking-wider flex items-center gap-1.5 shadow-[2px_2px_0px_#000]">
                    <i class="fa-solid fa-floppy-disk"></i> SIMPAN PERUBAHAN
                </button>
            </div>
        </div>
    </div>

    <!-- Hidden Server Initial Data for Seamless Hydration -->
    <script id="initial-notes-data" type="application/json">
        {!! json_encode($notes, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}
    </script>
    <script id="initial-categories-data" type="application/json">
        {!! json_encode($categories ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}
    </script>
    <input type="hidden" id="initial-active-note-id" value="{{ $activeNote->id ?? '' }}">

</body>
</html>
