<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Notenux') }} - Personalize Categories Guild</title>

    <!-- App Favicon & Touch Icon -->
    <link rel="icon" type="image/png" href="{{ asset('assets/logo/icon_dark.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('assets/logo/icon_dark.png') }}">

    <!-- Font Awesome 6 Free CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;600&family=Instrument+Sans:wght@400;500;600;700&family=Press+Start+2P&family=VT323&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="retro-crt-screen bg-[var(--bg-main)] text-[var(--text-main)] min-h-screen font-sans flex flex-col antialiased selection:bg-blue-600 selection:text-white">

    <!-- Top Retro Notification Toast Container -->
    <div id="toast-container" class="fixed top-4 right-4 z-50 flex flex-col gap-2 max-w-sm w-full pointer-events-none"></div>

    @if (session('message'))
        <div id="server-flash-message"
             data-type="{{ session('status', 'info') }}"
             data-message="{{ session('message') }}"
             class="hidden"></div>
    @endif

    <!-- Top Navigation Bar -->
    <header class="shrink-0 bg-[var(--bg-surface)] border-b-2 border-black dark:border-blue-900 shadow-[0_4px_0_rgba(0,0,0,0.25)] px-2 sm:px-4 lg:px-6 py-2 sm:py-2.5 z-30">
        <div class="flex items-center justify-between gap-1.5 sm:gap-4 max-w-7xl mx-auto">
            <!-- Left Branding & Back Link -->
            <div class="flex items-center gap-1.5 sm:gap-3 min-w-0">
                <a href="{{ route('studio') }}"
                   class="arcade-btn bg-blue-600 hover:bg-blue-500 text-white px-2.5 sm:px-3 py-1.5 rounded-lg text-xs font-mono tracking-wider flex items-center gap-1.5 shrink-0"
                   title="Return to Studio Grimoire">
                    <i class="fa-solid fa-arrow-left"></i>
                    <span class="hidden sm:inline">STUDIO</span>
                </a>

                <div class="flex items-center gap-1.5 sm:gap-2 min-w-0">
                    <!-- Adaptive Horizontal Logo -->
                    <img src="{{ asset('assets/logo/dark/icon_horizontal_dark.png') }}"
                         alt="Notenux Studio"
                         class="h-6 sm:h-7 w-auto object-contain hidden dark:block transition-transform hover:scale-105">
                    <img src="{{ asset('assets/logo/light/icon_horizontal_light.png') }}"
                         alt="Notenux Studio"
                         class="h-6 sm:h-7 w-auto object-contain block dark:hidden transition-transform hover:scale-105">
                    <div class="min-w-0">
                        <div class="flex items-center gap-1 sm:gap-1.5">
                            <span class="font-pixel text-xs sm:text-sm text-blue-600 dark:text-cyan-400 tracking-wider truncate">CATEGORY GUILD</span>
                            <span class="text-[9px] font-mono bg-blue-900/20 text-blue-600 dark:text-cyan-300 px-1 py-0.5 rounded border border-blue-500/40 font-bold uppercase hidden md:inline-block">PERSONALIZE</span>
                        </div>
                        <p class="text-[10px] font-mono opacity-60 hidden md:block truncate">Forge & Customize Grimoire Categories</p>
                    </div>
                </div>
            </div>

            <!-- Right Controls: SFX, CRT FX, Theme -->
            <div class="flex items-center gap-1 sm:gap-2 shrink-0">
                <button id="sound-toggle-btn"
                        class="arcade-btn bg-blue-100 hover:bg-blue-200 dark:bg-blue-950 dark:hover:bg-blue-900 text-blue-900 dark:text-blue-300 px-2 sm:px-2.5 py-1.5 rounded text-xs font-mono"
                        title="Toggle 8-bit Audio SFX">
                    <i class="fa-solid fa-volume-high text-blue-600 dark:text-cyan-400 sm:mr-1.5"></i> <span class="hidden sm:inline">SFX: ON</span>
                </button>

                <button id="crt-toggle-btn"
                        class="arcade-btn bg-purple-100 hover:bg-purple-200 dark:bg-purple-900/40 dark:hover:bg-purple-800/60 text-purple-900 dark:text-purple-300 px-2 sm:px-2.5 py-1.5 rounded text-xs font-mono hidden sm:inline-flex"
                        title="Toggle Retro CRT Scanlines">
                    <i class="fa-solid fa-tv sm:mr-1.5 text-purple-600 dark:text-purple-400"></i> <span class="hidden xl:inline">CRT FX</span>
                </button>

                <button id="theme-toggle-btn"
                        class="arcade-btn bg-amber-300 hover:bg-amber-400 dark:bg-blue-900 dark:hover:bg-blue-800 text-black dark:text-cyan-300 px-2 sm:px-2.5 py-1.5 rounded text-xs font-mono"
                        title="Toggle Light / Dark Mode">
                    <i class="fa-solid fa-moon text-cyan-300 sm:mr-1.5"></i> <span class="hidden sm:inline">NIGHT</span>
                </button>

                <!-- Back to Landing Page -->
                <a href="{{ route('landing') }}"
                   class="arcade-btn bg-slate-200 hover:bg-slate-300 dark:bg-slate-800 dark:hover:bg-slate-700 text-main px-2 sm:px-2.5 py-1.5 rounded text-xs font-mono"
                   title="Beranda Notenux">
                    <i class="fa-solid fa-house"></i>
                </a>

                <!-- Logout Button -->
                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button type="submit"
                            id="logout-btn"
                            class="arcade-btn bg-red-600 hover:bg-red-500 text-white px-2 sm:px-2.5 py-1.5 rounded text-xs font-mono"
                            title="Keluar">
                        <i class="fa-solid fa-right-from-bracket sm:mr-1"></i> <span class="hidden sm:inline">LOGOUT</span>
                    </button>
                </form>
            </div>
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="flex-1 max-w-7xl w-full mx-auto p-4 sm:p-6 lg:p-8 space-y-6">

        <!-- Top Scribe Profile Telemetry Bar -->
        <div class="arcade-card p-3 sm:p-4 rounded-xl flex items-center justify-between flex-wrap gap-3 shadow-[4px_4px_0px_#000]">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg border-2 border-black bg-blue-600 flex items-center justify-center text-white shrink-0 shadow-[2px_2px_0px_#000]">
                    <i class="fa-solid fa-hat-wizard text-lg"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="font-bold text-sm text-blue-900 dark:text-cyan-300">{{ $user->name }}</span>
                        <span class="font-pixel text-[9px] bg-blue-600 text-white px-1.5 py-0.5 rounded border border-black">
                            LV.{{ $stats['level'] ?? 1 }}
                        </span>
                        <span class="text-xs font-mono text-amber-600 dark:text-yellow-400"><i class="fa-solid fa-award mr-1"></i>{{ $stats['rank_title'] ?? 'Novice Scribe' }}</span>
                    </div>
                    <div class="text-[11px] font-mono text-gray-500 dark:text-gray-400 mt-0.5">
                        <span>Active Scribe Matrix</span> • <span class="text-blue-600 dark:text-cyan-400 font-bold">{{ $categories->count() }} Grimoire Categories Registered</span>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('studio') }}" class="arcade-btn bg-surface hover:bg-blue-100 dark:hover:bg-blue-900 text-xs font-mono px-3 py-1.5 rounded">
                    <i class="fa-solid fa-scroll mr-1 text-blue-600 dark:text-cyan-400"></i> Open Studio
                </a>
            </div>
        </div>

        <!-- 2-Column Guild Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

            <!-- Left Column: Forge New Category Form -->
            <div class="lg:col-span-5 arcade-card p-4 sm:p-5 rounded-xl shadow-[4px_4px_0px_#000]">
                <div class="flex items-center gap-2 pb-3 mb-4 border-b-2 border-dashed border-blue-500/30">
                    <i class="fa-solid fa-wand-sparkles text-blue-600 dark:text-cyan-400 text-lg"></i>
                    <div>
                        <h2 class="font-pixel text-xs sm:text-sm text-blue-900 dark:text-cyan-300">FORGE CATEGORY</h2>
                        <p class="text-[10px] font-mono text-gray-500 dark:text-gray-400">Personalize a new category tag for your scrolls</p>
                    </div>
                </div>

                <form action="{{ route('categories.store') }}" method="POST" class="space-y-4 font-mono text-xs" id="create-category-form">
                    @csrf

                    <!-- Category Name -->
                    <div>
                        <label for="name" class="block font-bold mb-1 text-xs">
                            <i class="fa-solid fa-pen-fancy text-blue-500 mr-1"></i> CATEGORY NAME <span class="text-rose-500">*</span>
                        </label>
                        <input type="text"
                               id="name"
                               name="name"
                               required
                               placeholder="e.g. Alchemy & Potions, Boss Tactics"
                               value="{{ old('name') }}"
                               class="w-full bg-surface-alt border-2 border-black/30 dark:border-blue-800 rounded p-2 text-xs focus:outline-none focus:border-cyan-400 shadow-[2px_2px_0px_#000]">
                        @error('name')
                            <p class="text-rose-500 text-[10px] mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Category Identifier (Slug) -->
                    <div>
                        <label for="slug" class="block font-bold mb-1 text-xs">
                            <i class="fa-solid fa-fingerprint text-blue-600 dark:text-cyan-400 mr-1"></i> SLUG / IDENTIFIER <span class="text-[10px] opacity-75 font-normal">(Auto-generated if empty)</span>
                        </label>
                        <input type="text"
                               id="slug"
                               name="slug"
                               placeholder="e.g. alchemy, boss-tactics"
                               value="{{ old('slug') }}"
                               class="w-full bg-surface-alt border-2 border-black/30 dark:border-blue-800 rounded p-2 text-xs focus:outline-none focus:border-cyan-400 shadow-[2px_2px_0px_#000]">
                        @error('slug')
                            <p class="text-rose-500 text-[10px] mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Color Theme Picker -->
                    <div>
                        <label class="block font-bold mb-1 text-xs">
                            <i class="fa-solid fa-palette text-amber-500 mr-1"></i> COLOR PALETTE
                        </label>
                        <input type="hidden" name="color" id="selected-color-input" value="{{ old('color', 'cyan') }}">
                        <div class="grid grid-cols-3 sm:grid-cols-6 gap-2 pt-1" id="color-palette-picker">
                            <button type="button" data-color="cyan" class="color-palette-btn p-2 rounded-lg border-2 border-black flex flex-col items-center gap-1 bg-cyan-500/20 text-cyan-700 dark:text-cyan-400 hover:scale-105 transition-transform ring-2 ring-cyan-400">
                                <span class="w-4 h-4 rounded-full bg-cyan-400 border border-black"></span>
                                <span class="text-[9px] font-bold">CYAN</span>
                            </button>
                            <button type="button" data-color="blue" class="color-palette-btn p-2 rounded-lg border-2 border-black flex flex-col items-center gap-1 bg-blue-500/20 text-blue-500 hover:scale-105 transition-transform">
                                <span class="w-4 h-4 rounded-full bg-blue-500 border border-black"></span>
                                <span class="text-[9px] font-bold">BLUE</span>
                            </button>
                            <button type="button" data-color="emerald" class="color-palette-btn p-2 rounded-lg border-2 border-black flex flex-col items-center gap-1 bg-emerald-500/20 text-emerald-500 hover:scale-105 transition-transform">
                                <span class="w-4 h-4 rounded-full bg-emerald-500 border border-black"></span>
                                <span class="text-[9px] font-bold">GREEN</span>
                            </button>
                            <button type="button" data-color="amber" class="color-palette-btn p-2 rounded-lg border-2 border-black flex flex-col items-center gap-1 bg-amber-500/20 text-amber-700 dark:text-amber-400 hover:scale-105 transition-transform">
                                <span class="w-4 h-4 rounded-full bg-amber-500 border border-black"></span>
                                <span class="text-[9px] font-bold">GOLD</span>
                            </button>
                            <button type="button" data-color="rose" class="color-palette-btn p-2 rounded-lg border-2 border-black flex flex-col items-center gap-1 bg-rose-500/20 text-rose-500 hover:scale-105 transition-transform">
                                <span class="w-4 h-4 rounded-full bg-rose-500 border border-black"></span>
                                <span class="text-[9px] font-bold">ROSE</span>
                            </button>
                            <button type="button" data-color="purple" class="color-palette-btn p-2 rounded-lg border-2 border-black flex flex-col items-center gap-1 bg-purple-500/20 text-purple-600 hover:scale-105 transition-transform">
                                <span class="w-4 h-4 rounded-full bg-purple-500 border border-black"></span>
                                <span class="text-[9px] font-bold">PURPLE</span>
                            </button>
                        </div>
                    </div>

                    <!-- Icon Preset Picker -->
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block font-bold text-xs">
                                <i class="fa-solid fa-icons text-purple-600 dark:text-purple-400 mr-1"></i> CATEGORY ICON
                            </label>
                            <div class="flex items-center gap-1 text-[11px] font-bold text-blue-600 dark:text-cyan-400">
                                <span>Preview:</span>
                                <i id="icon-live-preview" class="fa-solid fa-flask text-sm"></i>
                            </div>
                        </div>

                        <input type="text"
                               id="icon-input"
                               name="icon"
                               value="{{ old('icon', 'fa-solid fa-flask') }}"
                               placeholder="fa-solid fa-..."
                               class="w-full mb-2 bg-surface-alt border-2 border-black/30 dark:border-blue-800 rounded p-1.5 text-xs focus:outline-none focus:border-cyan-400 shadow-[1px_1px_0px_#000]">

                        <!-- Quick Icon Presets -->
                        <div class="grid grid-cols-4 sm:grid-cols-7 gap-1.5 p-2 bg-surface-alt rounded-lg border border-black/20" id="icon-presets-grid">
                            @php
                                $presets = [
                                    'fa-solid fa-flask' => 'Flask',
                                    'fa-solid fa-dragon' => 'Dragon',
                                    'fa-solid fa-skull' => 'Skull',
                                    'fa-solid fa-crown' => 'Crown',
                                    'fa-solid fa-gem' => 'Gem',
                                    'fa-solid fa-fire' => 'Fire',
                                    'fa-solid fa-ghost' => 'Ghost',
                                    'fa-solid fa-dice-d20' => 'D20',
                                    'fa-solid fa-compass' => 'Compass',
                                    'fa-solid fa-book-skull' => 'Grimoire',
                                    'fa-solid fa-hat-wizard' => 'Wizard',
                                    'fa-solid fa-dungeon' => 'Dungeon',
                                    'fa-solid fa-feather-pointed' => 'Quill',
                                    'fa-solid fa-shield-halved' => 'Shield',
                                ];
                            @endphp
                            @foreach ($presets as $cls => $label)
                                <button type="button"
                                        data-icon="{{ $cls }}"
                                        title="{{ $label }}"
                                        class="preset-icon-btn p-2 rounded bg-surface hover:bg-blue-200 dark:hover:bg-blue-800 border border-black/30 text-center transition-transform hover:scale-110 {{ $cls === 'fa-solid fa-flask' ? 'ring-2 ring-cyan-400 bg-cyan-100 dark:bg-cyan-900/60' : '' }}">
                                    <i class="{{ $cls }} text-sm"></i>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <!-- Description / Lore -->
                    <div>
                        <label for="description" class="block font-bold mb-1 text-xs">
                            <i class="fa-solid fa-align-left text-blue-400 mr-1"></i> DESCRIPTION & LORE
                        </label>
                        <textarea id="description"
                                  name="description"
                                  rows="2"
                                  placeholder="Brief description of scrolls stored in this category..."
                                  class="w-full bg-surface-alt border-2 border-black/30 dark:border-blue-800 rounded p-2 text-xs focus:outline-none focus:border-cyan-400 shadow-[2px_2px_0px_#000] resize-y">{{ old('description') }}</textarea>
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-2">
                        <button type="submit"
                                class="arcade-btn w-full bg-emerald-600 hover:bg-emerald-500 text-white py-2.5 px-4 rounded-lg text-xs font-mono font-bold tracking-wider flex items-center justify-center gap-2 shadow-[3px_3px_0px_#000]">
                            <i class="fa-solid fa-hammer"></i>
                            <span>FORGE CATEGORY NOW</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Right Column: Categories Matrix List -->
            <div class="lg:col-span-7 space-y-4">
                <div class="arcade-card p-4 sm:p-5 rounded-xl shadow-[4px_4px_0px_#000]">
                    <div class="flex items-center justify-between pb-3 mb-4 border-b-2 border-dashed border-blue-500/30 flex-wrap gap-2">
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-layer-group text-blue-600 dark:text-cyan-400 text-lg"></i>
                            <div>
                                <h2 class="font-pixel text-xs sm:text-sm text-blue-900 dark:text-cyan-300">CATEGORIES MATRIX</h2>
                                <p class="text-[10px] font-mono text-gray-500 dark:text-gray-400">All Grimoire categories available to your scribe</p>
                            </div>
                        </div>

                        <span class="text-xs font-mono font-bold bg-blue-100 dark:bg-blue-950 px-2.5 py-1 rounded border border-blue-500/40 text-blue-600 dark:text-cyan-300">
                            {{ $categories->count() }} Total
                        </span>
                    </div>

                    <!-- Categories Grid Cards -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" id="categories-grid-container">
                        @foreach ($categories as $cat)
                            @php
                                $colorClasses = match ($cat->color) {
                                    'amber' => 'border-amber-500 bg-amber-50 dark:bg-amber-950/30 text-amber-900 dark:text-amber-200',
                                    'purple' => 'border-purple-500 bg-purple-50 dark:bg-purple-950/30 text-purple-900 dark:text-purple-200',
                                    'rose' => 'border-rose-500 bg-rose-50 dark:bg-rose-950/30 text-rose-900 dark:text-rose-200',
                                    'cyan' => 'border-cyan-500 bg-cyan-50 dark:bg-cyan-950/30 text-cyan-900 dark:text-cyan-200',
                                    'emerald' => 'border-emerald-500 bg-emerald-50 dark:bg-emerald-950/30 text-emerald-900 dark:text-emerald-200',
                                    'blue' => 'border-blue-500 bg-blue-50 dark:bg-blue-950/30 text-blue-900 dark:text-blue-200',
                                    default => 'border-gray-500 bg-gray-50 dark:bg-slate-900/40 text-gray-900 dark:text-gray-200',
                                };
                            @endphp

                            <div class="category-card p-3 rounded-xl border-2 {{ $colorClasses }} shadow-[2px_2px_0px_#000] flex flex-col justify-between font-mono relative">
                                <div>
                                    <div class="flex items-center justify-between gap-1 mb-1.5">
                                        <div class="flex items-center gap-2">
                                            <span class="w-7 h-7 rounded border border-black/40 bg-surface flex items-center justify-center text-xs shrink-0 shadow-[1px_1px_0px_#000]">
                                                <i class="{{ $cat->icon }}"></i>
                                            </span>
                                            <div>
                                                <h3 class="font-bold text-xs">{{ $cat->name }}</h3>
                                                <code class="text-[10px] opacity-75">[{{ $cat->slug }}]</code>
                                            </div>
                                        </div>

                                        @if ($cat->is_system)
                                            <span class="text-[9px] uppercase font-pixel px-1.5 py-0.5 rounded bg-blue-600 text-white shrink-0 border border-black">
                                                SYSTEM
                                            </span>
                                        @else
                                            <span class="text-[9px] uppercase font-pixel px-1.5 py-0.5 rounded bg-emerald-600 text-white shrink-0 border border-black">
                                                CUSTOM
                                            </span>
                                        @endif
                                    </div>

                                    @if ($cat->description)
                                        <p class="text-[11px] opacity-80 my-1 line-clamp-2">{{ $cat->description }}</p>
                                    @endif
                                </div>

                                <div class="flex items-center justify-between pt-2.5 mt-2 border-t border-black/10 dark:border-white/10 text-[11px]">
                                    <span class="opacity-80">
                                        <i class="fa-solid fa-scroll text-[10px] mr-1"></i> {{ $cat->notes_count ?? 0 }} scrolls
                                    </span>

                                    <div class="flex items-center gap-1.5">
                                        <!-- Edit Trigger -->
                                        <button type="button"
                                                onclick="openEditCategoryModal({{ json_encode($cat) }})"
                                                class="arcade-btn bg-surface hover:bg-cyan-200 dark:hover:bg-cyan-900/60 px-2 py-0.5 rounded text-[10px] font-bold"
                                                title="Edit Category">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>

                                        <!-- Delete Trigger -->
                                        <button type="button"
                                                onclick="openDeleteCategoryModal({{ json_encode($cat) }})"
                                                class="arcade-btn bg-rose-600 hover:bg-rose-500 text-white px-2 py-0.5 rounded text-[10px] font-bold"
                                                title="Delete Category">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- ============================================================================== -->
    <!-- EDIT CATEGORY MODAL -->
    <!-- ============================================================================== -->
    <div id="edit-category-modal" class="fixed inset-0 z-50 bg-black/75 backdrop-blur-xs flex items-center justify-center p-3 sm:p-6 hidden">
        <div class="arcade-card w-full max-w-md bg-[var(--bg-surface)] rounded-xl border-4 border-cyan-500 shadow-[8px_8px_0px_#000] flex flex-col overflow-hidden">
            <div class="p-3.5 bg-blue-600 text-white flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-pen-to-square text-lg text-cyan-300"></i>
                    <h3 class="font-pixel text-xs tracking-wider">EDIT PERSONALIZED CATEGORY</h3>
                </div>
                <button type="button" onclick="closeEditCategoryModal()" class="arcade-btn bg-black text-white px-2 py-0.5 rounded text-xs font-mono">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form id="edit-category-form" method="POST" class="p-4 space-y-3 font-mono text-xs">
                @csrf
                @method('PUT')

                <div>
                    <label class="block font-bold mb-1">Category Name</label>
                    <input type="text" id="edit-name-input" name="name" required class="w-full p-2 bg-surface-alt border border-black/30 rounded text-xs focus:outline-none">
                </div>

                <div>
                    <label class="block font-bold mb-1">Slug / Identifier</label>
                    <input type="text" id="edit-slug-input" name="slug" placeholder="Auto-maintained if unchanged" class="w-full p-2 bg-surface-alt border border-black/30 rounded text-xs focus:outline-none">
                </div>

                <div>
                    <label class="block font-bold mb-1">Color Palette</label>
                    <select id="edit-color-select" name="color" class="w-full p-2 bg-surface-alt border border-black/30 rounded text-xs focus:outline-none">
                        <option value="cyan">Neon Cyan</option>
                        <option value="blue">Arcade Blue</option>
                        <option value="emerald">Emerald Glow</option>
                        <option value="amber">Pixel Gold</option>
                        <option value="rose">Crimson Red</option>
                        <option value="purple">Arcane Purple</option>
                    </select>
                </div>

                <div>
                    <label class="block font-bold mb-1">Icon (Font Awesome Class)</label>
                    <input type="text" id="edit-icon-input" name="icon" class="w-full p-2 bg-surface-alt border border-black/30 rounded text-xs focus:outline-none">
                </div>

                <div>
                    <label class="block font-bold mb-1">Description</label>
                    <textarea id="edit-description-input" name="description" rows="2" class="w-full p-2 bg-surface-alt border border-black/30 rounded text-xs focus:outline-none resize-y"></textarea>
                </div>

                <div class="pt-2 flex justify-end gap-2 border-t border-black/10">
                    <button type="button" onclick="closeEditCategoryModal()" class="arcade-btn bg-surface px-3 py-1.5 rounded text-xs">Cancel</button>
                    <button type="submit" class="arcade-btn bg-cyan-600 hover:bg-cyan-500 text-white px-4 py-1.5 rounded text-xs font-bold">
                        <i class="fa-solid fa-check mr-1"></i> SAVE CHANGES
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ============================================================================== -->
    <!-- DELETE CATEGORY CONFIRMATION MODAL -->
    <!-- ============================================================================== -->
    <div id="delete-category-modal" class="fixed inset-0 z-50 bg-black/75 backdrop-blur-xs flex items-center justify-center p-3 sm:p-6 hidden">
        <div class="arcade-card w-full max-w-md bg-[var(--bg-surface)] rounded-xl border-4 border-rose-600 shadow-[8px_8px_0px_#000] flex flex-col overflow-hidden">
            <div class="p-3.5 bg-rose-600 text-white flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-triangle-exclamation text-lg text-yellow-300"></i>
                    <h3 class="font-pixel text-xs tracking-wider">BANISH CATEGORY</h3>
                </div>
                <button type="button" onclick="closeDeleteCategoryModal()" class="arcade-btn bg-black text-white px-2 py-0.5 rounded text-xs font-mono">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="p-4 space-y-3 font-mono text-xs">
                <p>Are you sure you want to dismantle category <span id="delete-category-name-badge" class="font-bold text-rose-500"></span>?</p>
                <div class="p-2.5 bg-rose-100 dark:bg-rose-950/40 border border-rose-300 dark:border-rose-800 rounded text-rose-900 dark:text-rose-200 text-[11px]">
                    <i class="fa-solid fa-triangle-exclamation text-rose-500 mr-1"></i>
                    <strong>Peringatan / Warning:</strong> Semua catatan atau scroll yang terhubung dengan kategori ini akan otomatis ikut terhapus secara permanen.
                </div>
            </div>

            <div class="p-3 border-t border-black/10 bg-surface-alt flex justify-end gap-2 font-mono text-xs">
                <button type="button" onclick="closeDeleteCategoryModal()" class="arcade-btn bg-surface px-3 py-1.5 rounded">Cancel</button>
                <form id="delete-category-form" method="POST">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="arcade-btn bg-rose-600 hover:bg-rose-500 text-white px-4 py-1.5 rounded font-bold">
                        <i class="fa-solid fa-trash mr-1"></i> YES, BANISH CATEGORY
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Interactive Scripts for Category Guild -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Flash message toast
            const flash = document.getElementById('server-flash-message');
            if (flash && window.notenux?.toast) {
                window.notenux.toast.show(flash.dataset.message, flash.dataset.type === 'error' ? 'danger' : 'success');
            }

            // Live icon preview
            const iconInput = document.getElementById('icon-input');
            const iconPreview = document.getElementById('icon-live-preview');
            iconInput?.addEventListener('input', () => {
                if (iconPreview) iconPreview.className = iconInput.value || 'fa-solid fa-folder';
            });

            // Preset icon clicks
            document.querySelectorAll('.preset-icon-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const icon = btn.dataset.icon;
                    if (iconInput) {
                        iconInput.value = icon;
                        if (iconPreview) iconPreview.className = icon;
                    }
                    document.querySelectorAll('.preset-icon-btn').forEach(b => {
                        b.classList.remove('ring-2', 'ring-cyan-400', 'bg-cyan-100', 'dark:bg-cyan-900/60');
                    });
                    btn.classList.add('ring-2', 'ring-cyan-400', 'bg-cyan-100', 'dark:bg-cyan-900/60');
                });
            });

            // Color palette clicks
            const colorInput = document.getElementById('selected-color-input');
            document.querySelectorAll('.color-palette-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const color = btn.dataset.color;
                    if (colorInput) colorInput.value = color;
                    document.querySelectorAll('.color-palette-btn').forEach(b => {
                        b.classList.remove('ring-2', 'ring-cyan-400');
                    });
                    btn.classList.add('ring-2', 'ring-cyan-400');
                });
            });

            // Close modals on backdrop click
            ['edit-category-modal', 'delete-category-modal'].forEach(id => {
                const el = document.getElementById(id);
                el?.addEventListener('click', (e) => {
                    if (e.target === el) el.classList.add('hidden');
                });
            });
        });

        function openEditCategoryModal(category) {
            const modal = document.getElementById('edit-category-modal');
            const form = document.getElementById('edit-category-form');
            if (!modal || !form) return;

            form.action = `/categories/${category.id}`;
            document.getElementById('edit-name-input').value = category.name || '';
            const slugInput = document.getElementById('edit-slug-input');
            if (slugInput) slugInput.value = category.slug || '';
            document.getElementById('edit-color-select').value = category.color || 'cyan';
            document.getElementById('edit-icon-input').value = category.icon || 'fa-solid fa-folder';
            document.getElementById('edit-description-input').value = category.description || '';

            modal.classList.remove('hidden');
        }

        function closeEditCategoryModal() {
            document.getElementById('edit-category-modal')?.classList.add('hidden');
        }

        function openDeleteCategoryModal(category) {
            const modal = document.getElementById('delete-category-modal');
            const form = document.getElementById('delete-category-form');
            const badge = document.getElementById('delete-category-name-badge');
            if (!modal || !form) return;

            form.action = `/categories/${category.id}`;
            if (badge) badge.textContent = `"${category.name}"`;

            modal.classList.remove('hidden');
        }

        function closeDeleteCategoryModal() {
            document.getElementById('delete-category-modal')?.classList.add('hidden');
        }
    </script>
</body>
</html>
