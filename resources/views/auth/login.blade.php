<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Masuk ke Studio - {{ config('app.name', 'Notenux') }}</title>

    <!-- Font Awesome 6 Free CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <!-- Google Fonts for Retro Aesthetics -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;600&family=Instrument+Sans:wght@400;500;600;700&family=Press+Start+2P&family=VT323&display=swap" rel="stylesheet">

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
<body class="retro-crt-screen bg-[var(--bg-main)] text-[var(--text-main)] min-h-screen font-sans flex flex-col justify-between antialiased selection:bg-blue-600 selection:text-white transition-colors duration-200">

    <!-- Top Navigation Header -->
    <header class="w-full bg-[var(--bg-surface)] border-b-2 border-black dark:border-blue-900 shadow-[0_4px_0_rgba(0,0,0,0.25)] px-4 py-3 z-30">
        <div class="max-w-6xl mx-auto flex items-center justify-between">
            <a href="{{ route('landing') }}" class="flex items-center gap-2 group transition-transform active:translate-y-0.5">
                <i class="fa-solid fa-gamepad text-2xl text-blue-600 dark:text-cyan-400 group-hover:rotate-12 transition-transform"></i>
                <div class="flex items-center gap-1.5">
                    <span class="font-pixel text-sm sm:text-base text-blue-600 dark:text-cyan-400 tracking-wider">NOTENUX</span>
                    <span class="text-[9px] font-mono bg-blue-900/20 text-blue-600 dark:text-cyan-300 px-1.5 py-0.5 rounded border border-blue-500/40 font-bold uppercase">AUTH</span>
                </div>
            </a>

            <div class="flex items-center gap-2.5">
                <!-- Theme Toggle Button -->
                <button id="theme-toggle-btn"
                        class="arcade-btn bg-amber-300 hover:bg-amber-400 dark:bg-blue-900 dark:hover:bg-blue-800 text-black dark:text-cyan-300 px-2.5 py-1.5 rounded text-xs font-mono"
                        title="Toggle Light / Dark Mode">
                    <i class="fa-solid fa-moon text-cyan-300 mr-1.5"></i> <span class="hidden sm:inline">THEME</span>
                </button>

                <a href="{{ route('landing') }}" class="arcade-btn bg-slate-200 hover:bg-slate-300 dark:bg-slate-800 dark:hover:bg-slate-700 text-xs font-mono px-3 py-1.5 rounded">
                    <i class="fa-solid fa-house mr-1"></i> <span class="hidden sm:inline">Beranda</span>
                </a>
            </div>
        </div>
    </header>

    <!-- Main Auth Card Container -->
    <main class="flex-1 flex items-center justify-center p-4 sm:p-6 lg:p-8">
        <div class="w-full max-w-md">

            <!-- Gamified Notice Banner -->
            <div class="mb-4 arcade-card p-3 rounded-lg border-2 border-blue-500 bg-blue-50 dark:bg-blue-950/60 shadow-[3px_3px_0px_#000] text-xs font-mono">
                <div class="flex items-start gap-2.5">
                    <i class="fa-solid fa-shield-halved text-base text-blue-600 dark:text-cyan-400 mt-0.5 shrink-0"></i>
                    <div>
                        <span class="font-bold text-blue-900 dark:text-cyan-300">Autentikasi Studio Diperlukan</span>
                        <p class="text-slate-600 dark:text-slate-300 mt-0.5 leading-relaxed">
                            Akses ke <strong>/studio</strong> memerlukan akun Scribe aktif. Belum memiliki akun?
                            <a href="{{ route('register') }}" class="text-blue-600 dark:text-cyan-400 underline font-bold hover:text-blue-700">Daftar terlebih dahulu di sini</a>.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Login Box Card -->
            <div class="arcade-card p-6 sm:p-8 rounded-xl border-2 border-black dark:border-blue-700 bg-[var(--bg-surface)] shadow-[6px_6px_0px_#000] space-y-6">

                <!-- Header Title -->
                <div class="text-center space-y-1.5">
                    <div class="inline-flex items-center justify-center w-12 h-12 rounded-lg bg-blue-600 text-white border-2 border-black shadow-[2px_2px_0px_#000] mb-1">
                        <i class="fa-solid fa-key text-xl"></i>
                    </div>
                    <h1 class="font-pixel text-sm sm:text-base text-blue-950 dark:text-cyan-300 tracking-wider">MASUK SCRIBE</h1>
                    <p class="text-xs font-mono text-slate-600 dark:text-slate-400">Masukkan kredensial untuk membuka Studio Notenux</p>
                </div>

                <!-- Error Messages Display -->
                @if ($errors->any())
                    <div class="p-3 rounded-lg border-2 border-red-500 bg-red-100 dark:bg-red-950/50 text-red-800 dark:text-red-300 text-xs font-mono space-y-1">
                        <div class="flex items-center gap-1.5 font-bold">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                            <span>Gagal Masuk:</span>
                        </div>
                        <ul class="list-disc list-inside space-y-0.5 pl-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <!-- Form -->
                <form method="POST" action="{{ route('login') }}" class="space-y-4 font-mono text-xs">
                    @csrf

                    <!-- Email Field -->
                    <div class="space-y-1.5">
                        <label for="email" class="block font-bold text-slate-800 dark:text-slate-200">
                            <i class="fa-solid fa-envelope text-blue-500 mr-1"></i> EMAIL SCRIBE
                        </label>
                        <input id="email"
                               type="email"
                               name="email"
                               value="{{ old('email') }}"
                               required
                               autofocus
                               autocomplete="username"
                               placeholder="scribe@notenux.dev"
                               class="w-full px-3.5 py-2.5 rounded-lg border-2 border-black/40 dark:border-blue-500/50 bg-[var(--bg-main)] text-[var(--text-main)] focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20 text-xs font-mono transition-colors">
                    </div>

                    <!-- Password Field -->
                    <div class="space-y-1.5">
                        <label for="password" class="block font-bold text-slate-800 dark:text-slate-200">
                            <i class="fa-solid fa-lock text-blue-500 mr-1"></i> KATA SANDI
                        </label>
                        <input id="password"
                               type="password"
                               name="password"
                               required
                               autocomplete="current-password"
                               placeholder="••••••••"
                               class="w-full px-3.5 py-2.5 rounded-lg border-2 border-black/40 dark:border-blue-500/50 bg-[var(--bg-main)] text-[var(--text-main)] focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20 text-xs font-mono transition-colors">
                    </div>

                    <!-- Remember Me Checkbox -->
                    <div class="flex items-center justify-between pt-1">
                        <label class="flex items-center gap-2 cursor-pointer select-none">
                            <input type="checkbox"
                                   name="remember"
                                   class="rounded border-2 border-black dark:border-blue-400 text-blue-600 focus:ring-0 w-4 h-4 cursor-pointer">
                            <span class="text-slate-600 dark:text-slate-400 font-bold">Ingat sesi ini</span>
                        </label>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit"
                            id="login-submit-btn"
                            class="arcade-btn w-full py-3 px-4 rounded-lg bg-blue-600 hover:bg-blue-500 text-white font-pixel text-xs tracking-wider shadow-[3px_3px_0px_#000] active:translate-y-0.5 active:shadow-[1px_1px_0px_#000] flex items-center justify-center gap-2 transition-all">
                        <i class="fa-solid fa-right-to-bracket text-sm"></i>
                        <span>MASUK KE STUDIO</span>
                    </button>
                </form>

                <!-- Divider & Register Prompt -->
                <div class="pt-4 border-t-2 border-dashed border-black/10 dark:border-white/10 text-center space-y-3 font-mono text-xs">
                    <p class="text-slate-600 dark:text-slate-400">
                        Belum memiliki akun Scribe?
                    </p>
                    <a href="{{ route('register') }}"
                       id="to-register-btn"
                       class="arcade-btn inline-flex items-center justify-center gap-2 w-full py-2.5 px-4 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-mono font-bold text-xs tracking-wider shadow-[3px_3px_0px_#000] transition-colors">
                        <i class="fa-solid fa-user-plus"></i>
                        <span>REGISTER AKUN BARU</span>
                    </a>
                </div>

            </div>

            <!-- Footer note -->
            <p class="mt-6 text-center text-[10px] font-mono text-slate-500 dark:text-slate-400">
                Notenux Studio &copy; {{ date('Y') }} &bull; Scribe Memory Matrix
            </p>
        </div>
    </main>

</body>
</html>
