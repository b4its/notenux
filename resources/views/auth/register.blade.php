<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark" data-theme="dark" itemscope itemtype="https://schema.org/WebPage">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Primary SEO Metadata -->
    <title>Daftar Scribe Baru - Notenux | Mulai Petualangan Menulis</title>
    <meta name="title" content="Daftar Scribe Baru - Notenux | Mulai Petualangan Menulis">
    <meta name="description" content="Daftar akun Notenux gratis (notenux.oryphem.com/register). Nikmati sensasi menulis catatan retro modern, sistem leveling XP RPG, dan proteksi naskah otomatis.">
    <meta name="keywords" content="register notenux, daftar akun notenux, notenux oryphem, https://notenux.oryphem.com/register, aplikasi catatan gratis, markdown registration, buat akun catatan">
    <meta name="author" content="Oryphem & Notenux Team">
    <meta name="publisher" content="https://notenux.oryphem.com">
    <link rel="canonical" href="https://notenux.oryphem.com/register">

    <!-- Alternate Languages / Hreflang -->
    <link rel="alternate" hreflang="id" href="https://notenux.oryphem.com/register">
    <link rel="alternate" hreflang="en" href="https://notenux.oryphem.com/register">
    <link rel="alternate" hreflang="x-default" href="https://notenux.oryphem.com/register">

    <!-- Dublin Core Metadata -->
    <meta name="DC.title" content="Daftar Scribe Baru - Notenux | Mulai Petualangan Menulis">
    <meta name="DC.creator" content="Oryphem">
    <meta name="DC.description" content="Daftar akun Notenux gratis (notenux.oryphem.com/register). Nikmati sensasi menulis catatan retro modern, leveling XP RPG, dan proteksi naskah otomatis.">
    <meta name="DC.publisher" content="https://notenux.oryphem.com">
    <meta name="DC.language" content="id">
    <meta name="DC.identifier" content="https://notenux.oryphem.com/register">

    <!-- Schema.org Microdata -->
    <meta itemprop="name" content="Daftar Scribe Baru - Notenux | Mulai Petualangan Menulis">
    <meta itemprop="description" content="Daftar akun Notenux gratis (notenux.oryphem.com/register). Nikmati sensasi menulis catatan retro modern.">
    <meta itemprop="image" content="{{ asset('assets/logo/light/icon_horizontal_light.webp') }}">
    <meta itemprop="url" content="https://notenux.oryphem.com/register">

    <!-- Search Engine Crawlers Optimization -->
    <meta name="robots" content="index, follow">
    <meta name="googlebot" content="index, follow">
    <meta name="bingbot" content="index, follow">
    <meta name="slurp" content="index, follow">
    <meta name="duckduckbot" content="index, follow">

    <!-- Open Graph / Social Media -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Notenux">
    <meta property="og:url" content="https://notenux.oryphem.com/register">
    <meta property="og:title" content="Daftar Scribe Baru - Notenux | Mulai Petualangan Menulis">
    <meta property="og:description" content="Daftar akun Notenux gratis. Tingkatkan level Scribe Anda dengan aplikasi catatan retro gamifikasi terbaik.">
    <meta property="og:image" content="{{ asset('assets/logo/light/icon_horizontal_light.webp') }}">
    <meta property="og:image:secure_url" content="{{ asset('assets/logo/light/icon_horizontal_light.webp') }}">
    <meta property="og:image:type" content="image/webp">
    <meta property="og:image:width" content="2059">
    <meta property="og:image:height" content="764">
    <meta property="og:locale" content="id_ID">
    <meta property="og:locale:alternate" content="en_US">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:site" content="@notenux">
    <meta name="twitter:creator" content="@oryphem">
    <meta name="twitter:domain" content="notenux.oryphem.com">
    <meta name="twitter:url" content="https://notenux.oryphem.com/register">
    <meta name="twitter:title" content="Daftar Scribe Baru - Notenux | Mulai Petualangan Menulis">
    <meta name="twitter:description" content="Daftar akun Notenux gratis. Mulai petualangan menulis retro gamifikasi sekarang.">
    <meta name="twitter:image" content="{{ asset('assets/logo/light/icon_horizontal_light.webp') }}">

    <!-- Mobile & PWA Settings -->
    <meta name="application-name" content="Notenux">
    <meta name="apple-mobile-web-app-title" content="Notenux">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="msapplication-TileColor" content="#030712">
    <meta name="msapplication-TileImage" content="{{ asset('assets/logo/light/icon_light.webp') }}">
    <meta name="theme-color" content="#030712" media="(prefers-color-scheme: dark)">
    <meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">

    <!-- App Favicon & Touch Icon (Default Light, dynamically adapts to theme) -->
    <link rel="icon" id="app-favicon" type="image/webp" href="{{ asset('assets/logo/light/icon_light.webp') }}">
    <link rel="apple-touch-icon" id="app-apple-touch-icon" href="{{ asset('assets/logo/light/icon_light.webp') }}">

    <!-- DNS-Prefetch and Preconnect -->
    <link rel="dns-prefetch" href="//fonts.googleapis.com">
    <link rel="dns-prefetch" href="//fonts.gstatic.com">
    <link rel="dns-prefetch" href="//cdnjs.cloudflare.com">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <!-- Font Awesome 6 Free CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <!-- Google Fonts for Retro Aesthetics -->
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;600&family=Instrument+Sans:wght@400;500;600;700&family=Press+Start+2P&family=VT323&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Structured Data (JSON-LD Breadcrumbs) -->
    <script type="application/ld+json">
    {
      "@@context": "https://schema.org",
      "@type": "BreadcrumbList",
      "itemListElement": [
        {
          "@type": "ListItem",
          "position": 1,
          "name": "Beranda Notenux",
          "item": "https://notenux.oryphem.com/"
        },
        {
          "@type": "ListItem",
          "position": 2,
          "name": "Daftar Scribe Baru",
          "item": "https://notenux.oryphem.com/register"
        }
      ]
    }
    </script>

    <script>
        (function() {
            const savedTheme = localStorage.getItem('notenux_theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            const lightFavicon = "{{ asset('assets/logo/light/icon_light.webp') }}";
            const darkFavicon = "{{ asset('assets/logo/dark/icon_dark.webp') }}";
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
<body class="retro-crt-screen bg-[var(--bg-main)] text-[var(--text-main)] min-h-screen font-sans flex flex-col justify-between antialiased selection:bg-blue-600 selection:text-white transition-colors duration-200">

    <!-- Top Navigation Header -->
    <header class="w-full bg-[var(--bg-surface)] border-b-2 border-black dark:border-blue-900 shadow-[0_4px_0_rgba(0,0,0,0.25)] px-3 sm:px-6 py-2.5 sm:py-3 z-30">
        <div class="max-w-6xl mx-auto flex items-center justify-between gap-2">
            <a href="{{ route('landing') }}" class="flex items-center gap-1.5 sm:gap-2 group transition-transform active:translate-y-0.5 shrink-0 min-w-0">
                <!-- Adaptive Horizontal Logo -->
                <img src="{{ asset('assets/logo/dark/icon_horizontal_dark.webp') }}"
                     alt="Notenux Studio"
                     class="h-7 sm:h-8 w-auto object-contain hidden dark:block transition-transform group-hover:scale-105"
                     decoding="async">
                <img src="{{ asset('assets/logo/light/icon_horizontal_light.webp') }}"
                     alt="Notenux Studio"
                     class="h-7 sm:h-8 w-auto object-contain block dark:hidden transition-transform group-hover:scale-105"
                     decoding="async">
                <span class="sr-only">NOTENUX</span>
                <span class="text-[9px] font-mono bg-blue-900/20 text-blue-600 dark:text-cyan-300 px-1.5 py-0.5 rounded border border-blue-500/40 font-bold uppercase hidden xs:inline-block">REGISTER</span>
            </a>

            <div class="flex items-center gap-2 sm:gap-2.5 shrink-0">
                <!-- Theme Toggle Button -->
                <button id="theme-toggle-btn"
                        class="arcade-btn bg-amber-300 hover:bg-amber-400 dark:bg-blue-900 dark:hover:bg-blue-800 text-black dark:text-cyan-300 px-2 sm:px-2.5 py-1.5 rounded text-xs font-mono"
                        title="Toggle Light / Dark Mode">
                    <i class="fa-solid fa-moon text-cyan-300 sm:mr-1.5"></i> <span class="hidden sm:inline">THEME</span>
                </button>

                <a href="{{ route('landing') }}" class="arcade-btn bg-slate-200 hover:bg-slate-300 dark:bg-slate-800 dark:hover:bg-slate-700 text-xs font-mono px-2.5 sm:px-3 py-1.5 rounded">
                    <i class="fa-solid fa-house sm:mr-1"></i> <span class="hidden sm:inline">Beranda</span>
                </a>
            </div>
        </div>
    </header>

    <!-- Main Registration Container -->
    <main class="flex-1 flex items-center justify-center p-4 sm:p-6 lg:p-8">
        <div class="w-full max-w-md">

            <!-- Gamified Notice Banner -->
            <div class="mb-4 arcade-card p-3 rounded-lg border-2 border-emerald-500 bg-emerald-50 dark:bg-emerald-950/60 shadow-[3px_3px_0px_#000] text-xs font-mono">
                <div class="flex items-start gap-2.5">
                    <i class="fa-solid fa-hat-wizard text-base text-emerald-600 dark:text-emerald-400 mt-0.5 shrink-0"></i>
                    <div>
                        <span class="font-bold text-emerald-900 dark:text-emerald-300">Bergabung Sebagai Scribe Baru</span>
                        <p class="text-slate-600 dark:text-slate-300 mt-0.5 leading-relaxed">
                            Buat akun untuk memulai petualangan catatan di Studio, membuka XP leveling, spellcasting AI, dan sistem keamanan dokumen terenkripsi.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Registration Card -->
            <div class="arcade-card p-6 sm:p-8 rounded-xl border-2 border-black dark:border-blue-700 bg-[var(--bg-surface)] shadow-[6px_6px_0px_#000] space-y-6">

                <!-- Header Title -->
                <div class="text-center space-y-2">
                    <div class="flex justify-center mb-1">
                        <img src="{{ asset('assets/logo/dark/icon_vertical_dark.webp') }}"
                             alt="Notenux Scribe"
                             class="w-16 sm:w-20 h-auto object-contain hidden dark:block mx-auto drop-shadow-[0_4px_8px_rgba(0,0,0,0.5)]"
                             fetchpriority="high"
                             decoding="async">
                        <img src="{{ asset('assets/logo/light/icon_vertical_light.webp') }}"
                             alt="Notenux Scribe"
                             class="w-16 sm:w-20 h-auto object-contain block dark:hidden mx-auto drop-shadow-[0_2px_4px_rgba(0,0,0,0.1)]"
                             fetchpriority="high"
                             decoding="async">
                    </div>
                    <h1 class="font-pixel text-sm sm:text-base text-blue-950 dark:text-cyan-300 tracking-wider">REGISTER SCRIBE</h1>
                    <p class="text-xs font-mono text-slate-600 dark:text-slate-400">Daftarkan identitas untuk mengakses Studio Notenux</p>
                </div>

                <!-- Error Messages Display -->
                @if ($errors->any())
                    <div class="p-3 rounded-lg border-2 border-red-500 bg-red-100 dark:bg-red-950/50 text-red-800 dark:text-red-300 text-xs font-mono space-y-1">
                        <div class="flex items-center gap-1.5 font-bold">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                            <span>Gagal Mendaftar:</span>
                        </div>
                        <ul class="list-disc list-inside space-y-0.5 pl-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <!-- Form -->
                <form method="POST" action="{{ route('register') }}" class="space-y-4 font-mono text-xs">
                    @csrf

                    <!-- Name Field -->
                    <div class="space-y-1.5">
                        <label for="name" class="block font-bold text-slate-800 dark:text-slate-200">
                            <i class="fa-solid fa-user-ninja text-emerald-500 mr-1"></i> NAMA SCRIBE / CODENAME
                        </label>
                        <input id="name"
                               type="text"
                               name="name"
                               value="{{ old('name') }}"
                               required
                               autofocus
                               autocomplete="name"
                               placeholder="Contoh: Archie Chronos"
                               class="w-full px-3.5 py-2.5 rounded-lg border-2 border-black/40 dark:border-blue-500/50 bg-[var(--bg-main)] text-[var(--text-main)] focus:outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-500/20 text-xs font-mono transition-colors">
                    </div>

                    <!-- Email Field -->
                    <div class="space-y-1.5">
                        <label for="email" class="block font-bold text-slate-800 dark:text-slate-200">
                            <i class="fa-solid fa-envelope text-emerald-500 mr-1"></i> EMAIL SCRIBE
                        </label>
                        <input id="email"
                               type="email"
                               name="email"
                               value="{{ old('email') }}"
                               required
                               autocomplete="username"
                               placeholder="scribe@notenux.dev"
                               class="w-full px-3.5 py-2.5 rounded-lg border-2 border-black/40 dark:border-blue-500/50 bg-[var(--bg-main)] text-[var(--text-main)] focus:outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-500/20 text-xs font-mono transition-colors">
                    </div>

                    <!-- Password Field -->
                    <div class="space-y-1.5">
                        <label for="password" class="block font-bold text-slate-800 dark:text-slate-200">
                            <i class="fa-solid fa-lock text-emerald-500 mr-1"></i> KATA SANDI (MIN. 8 KARAKTER)
                        </label>
                        <div class="relative">
                            <input id="password"
                                   type="password"
                                   name="password"
                                   required
                                   autocomplete="new-password"
                                   placeholder="••••••••"
                                   class="w-full pl-3.5 pr-10 py-2.5 rounded-lg border-2 border-black/40 dark:border-blue-500/50 bg-[var(--bg-main)] text-[var(--text-main)] focus:outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-500/20 text-xs font-mono transition-colors">
                            <button type="button"
                                    id="toggle-password-btn"
                                    class="toggle-password-btn absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400 p-1.5 rounded transition-colors focus:outline-none cursor-pointer flex items-center justify-center"
                                    data-target="password"
                                    data-state="closed"
                                    aria-label="Tampilkan kata sandi"
                                    title="Tampilkan kata sandi (Show password)">
                                <i class="fa-solid fa-eye text-xs toggle-password-icon"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Password Confirmation Field -->
                    <div class="space-y-1.5">
                        <label for="password_confirmation" class="block font-bold text-slate-800 dark:text-slate-200">
                            <i class="fa-solid fa-circle-check text-emerald-500 mr-1"></i> KONFIRMASI KATA SANDI
                        </label>
                        <div class="relative">
                            <input id="password_confirmation"
                                   type="password"
                                   name="password_confirmation"
                                   required
                                   autocomplete="new-password"
                                   placeholder="••••••••"
                                   class="w-full pl-3.5 pr-10 py-2.5 rounded-lg border-2 border-black/40 dark:border-blue-500/50 bg-[var(--bg-main)] text-[var(--text-main)] focus:outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-500/20 text-xs font-mono transition-colors">
                            <button type="button"
                                    id="toggle-password-confirmation-btn"
                                    class="toggle-password-btn absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400 p-1.5 rounded transition-colors focus:outline-none cursor-pointer flex items-center justify-center"
                                    data-target="password_confirmation"
                                    data-state="closed"
                                    aria-label="Tampilkan konfirmasi kata sandi"
                                    title="Tampilkan konfirmasi kata sandi (Show password)">
                                <i class="fa-solid fa-eye text-xs toggle-password-icon"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit"
                            id="register-submit-btn"
                            class="arcade-btn w-full py-3 px-4 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-pixel text-xs tracking-wider shadow-[3px_3px_0px_#000] active:translate-y-0.5 active:shadow-[1px_1px_0px_#000] flex items-center justify-center gap-2 transition-all">
                        <i class="fa-solid fa-hat-wizard text-sm"></i>
                        <span>DAFTAR & MASUK STUDIO</span>
                    </button>
                </form>

                <!-- Divider & Login Prompt -->
                <div class="pt-4 border-t-2 border-dashed border-black/10 dark:border-white/10 text-center space-y-3 font-mono text-xs">
                    <p class="text-slate-600 dark:text-slate-400">
                        Sudah memiliki akun Scribe?
                    </p>
                    <a href="{{ route('login') }}"
                       id="to-login-btn"
                       class="arcade-btn inline-flex items-center justify-center gap-2 w-full py-2.5 px-4 rounded-lg bg-blue-600 hover:bg-blue-500 text-white font-mono font-bold text-xs tracking-wider shadow-[3px_3px_0px_#000] transition-colors">
                        <i class="fa-solid fa-right-to-bracket"></i>
                        <span>MASUK KE AKUN ANDA</span>
                    </a>
                </div>

            </div>

            <!-- Footer note -->
            <p class="mt-6 text-center text-[10px] font-mono text-slate-500 dark:text-slate-400">
                Notenux Studio &copy; {{ date('Y') }} &bull; Scribe Memory Matrix
            </p>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (window.RetroPasswordToggle) {
                window.RetroPasswordToggle.initAll();
            } else {
                document.querySelectorAll('.toggle-password-btn').forEach(function(btn) {
                    if (btn.dataset.boundPasswordToggle) return;
                    btn.dataset.boundPasswordToggle = 'true';
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        var targetId = btn.getAttribute('data-target');
                        var input = targetId ? document.getElementById(targetId) : btn.parentElement.querySelector('input');
                        if (!input) return;
                        var icon = btn.querySelector('.toggle-password-icon') || btn.querySelector('i');
                        var isPassword = input.type === 'password';
                        if (isPassword) {
                            input.type = 'text';
                            btn.setAttribute('data-state', 'open');
                            btn.setAttribute('title', 'Sembunyikan kata sandi (Close password)');
                            btn.setAttribute('aria-label', 'Sembunyikan kata sandi');
                            if (icon) {
                                icon.classList.remove('fa-eye');
                                icon.classList.add('fa-eye-slash');
                            }
                        } else {
                            input.type = 'password';
                            btn.setAttribute('data-state', 'closed');
                            btn.setAttribute('title', 'Tampilkan kata sandi (Show password)');
                            btn.setAttribute('aria-label', 'Tampilkan kata sandi');
                            if (icon) {
                                icon.classList.remove('fa-eye-slash');
                                icon.classList.add('fa-eye');
                            }
                        }
                    });
                });
            }
        });
    </script>
</body>
</html>
