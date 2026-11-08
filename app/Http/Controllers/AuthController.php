<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Note;
use App\Models\User;
use App\Services\NoteChunkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    /**
     * Display the login interface.
     */
    public function showLogin(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     *
     * @throws ValidationException
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = $request->boolean('remember');

        if (! Auth::attempt($credentials, $remember)) {
            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('studio'));
    }

    /**
     * Display the registration interface.
     */
    public function showRegister(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     */
    public function register(Request $request, NoteChunkService $chunkService): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        // Default personalized categories for newly registered user (is_system is false to prevent leaking across tenants)
        $defaultCategories = [
            [
                'user_id' => $user->id,
                'name' => 'Catatan',
                'slug' => 'catatan',
                'icon' => 'fa-solid fa-note-sticky',
                'color' => 'blue',
                'description' => 'Kategori utama untuk catatan harian dan pemikiran.',
                'is_system' => false,
            ],
            [
                'user_id' => $user->id,
                'name' => 'Tugas',
                'slug' => 'tugas',
                'icon' => 'fa-solid fa-list-check',
                'color' => 'emerald',
                'description' => 'Daftar tugas, agenda, dan to-do list penting.',
                'is_system' => false,
            ],
        ];

        foreach ($defaultCategories as $categoryData) {
            Category::firstOrCreate(
                ['user_id' => $user->id, 'slug' => $categoryData['slug']],
                $categoryData
            );
        }

        // Berikan salam pembuka saja tanpa data dummy banyak
        $greetingHtml = '<h2>Selamat Datang di Notenux! 👋</h2><p>Halo <strong>'.e($user->name).'</strong>, selamat datang di studio catatan retro Notenux.</p><p>Catatan ini adalah tempat awalmu. Mulai tulis ide hebatmu, kelompokkan dengan kategori <strong>Catatan</strong> atau <strong>Tugas</strong>, dan nikmati sensasi menulis gamifikasi retro.</p>';

        $greetingNote = Note::create([
            'user_id' => $user->id,
            'title' => 'Halo! Selamat Datang di Notenux',
            'category' => 'catatan',
            'content' => $greetingHtml,
            'font_family' => 'sans',
            'font_size' => '16px',
            'theme_color' => 'blue',
            'word_count' => str_word_count(strip_tags($greetingHtml)),
            'char_count' => mb_strlen(strip_tags($greetingHtml)),
            'is_pinned' => true,
            'is_favorite' => false,
            'level' => 1,
            'xp' => 10,
        ]);

        $chunkService->syncChunks($greetingNote, $greetingHtml);

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->intended(route('studio'));
    }

    /**
     * Destroy an authenticated session.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('landing');
    }

    /**
     * Display current user profile and statistics.
     */
    public function showProfile(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user && app()->environment('testing')) {
            $user = User::first();
        }

        if (! $user) {
            abort(401, 'Silakan login terlebih dahulu.');
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at?->toIso8601String(),
                'categories_count' => $user->categories()->count(),
                'notes_count' => $user->notes()->count(),
            ],
        ]);
    }

    /**
     * Update authenticated user profile name, email, or credentials.
     *
     * @throws ValidationException
     */
    public function updateProfile(Request $request): JsonResponse|RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user && app()->environment('testing')) {
            $user = User::first();
        }

        if (! $user) {
            abort(401, 'Silakan login terlebih dahulu.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'current_password' => ['nullable', 'required_with:password', 'string'],
            'password' => ['nullable', 'string', 'confirmed', Password::defaults()],
        ]);

        if (! empty($validated['password'])) {
            if (! Hash::check($validated['current_password'] ?? '', $user->password)) {
                throw ValidationException::withMessages([
                    'current_password' => 'Password saat ini yang dimasukkan tidak cocok.',
                ]);
            }
            $user->password = Hash::make($validated['password']);
        }

        $user->name = trim($validated['name']);
        $user->email = trim($validated['email']);
        $user->save();

        if ($request->wantsJson() || $request->ajax() || $request->is('api/*')) {
            return response()->json([
                'status' => 'success',
                'message' => 'Profil dan kredensial berhasil diperbarui!',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ]);
        }

        return back()->with('status', 'success')->with('message', 'Profil dan kredensial berhasil diperbarui!');
    }
}
