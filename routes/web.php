<?php

use App\Http\Controllers\AiAssistantController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\NoteController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public & Landing Routes
|--------------------------------------------------------------------------
*/
Route::get('/', [LandingController::class, 'index'])->name('landing');
Route::get('/sitemap.xml', function () {
    return response(file_get_contents(public_path('sitemap.xml')), 200, [
        'Content-Type' => 'application/xml; charset=UTF-8',
    ]);
})->name('sitemap');

/*
|--------------------------------------------------------------------------
| Guest Authentication Routes
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:20,1');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
});

/*
|--------------------------------------------------------------------------
| Authenticated User Session Routes
|--------------------------------------------------------------------------
*/
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Studio Panel Routes (Authentication Required)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::get('/studio', [NoteController::class, 'index'])->name('studio');
    Route::get('/panel', fn () => redirect()->route('studio'))->name('panel');

    Route::get('/profile', [AuthController::class, 'showProfile'])->name('profile.show');
    Route::put('/profile', [AuthController::class, 'updateProfile'])->name('profile.update');
    Route::get('/api/profile', [AuthController::class, 'showProfile'])->name('api.profile.show');
    Route::put('/api/profile', [AuthController::class, 'updateProfile'])->name('api.profile.update');

    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::post('/categories/bulk-delete', [CategoryController::class, 'bulkDestroy'])->name('categories.bulk-delete');
    Route::delete('/categories/bulk-delete', [CategoryController::class, 'bulkDestroy']);
    Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
});

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/
Route::prefix('api/categories')->group(function () {
    Route::get('/', [CategoryController::class, 'index'])->name('api.categories.index');
    Route::post('/', [CategoryController::class, 'store'])->name('api.categories.store');
    Route::post('/bulk-delete', [CategoryController::class, 'bulkDestroy'])->name('api.categories.bulk-delete');
    Route::delete('/bulk-delete', [CategoryController::class, 'bulkDestroy']);
    Route::put('/{category}', [CategoryController::class, 'update'])->name('api.categories.update');
    Route::delete('/{category}', [CategoryController::class, 'destroy'])->name('api.categories.destroy');
});

Route::prefix('api/notes')->group(function () {
    Route::get('/', [NoteController::class, 'index'])->name('notes.index');
    Route::post('/', [NoteController::class, 'store'])->name('notes.store');
    Route::post('/import', [NoteController::class, 'import'])->middleware('throttle:30,1')->name('notes.import');
    Route::post('/bulk-delete', [NoteController::class, 'bulkDestroy'])->name('notes.bulk-delete');
    Route::delete('/bulk-delete', [NoteController::class, 'bulkDestroy']);
    Route::post('/bulk-update', [NoteController::class, 'bulkUpdate'])->name('notes.bulk-update');
    Route::put('/bulk-update', [NoteController::class, 'bulkUpdate']);
    Route::get('/{note}', [NoteController::class, 'show'])->name('notes.show');
    Route::put('/{note}', [NoteController::class, 'update'])->name('notes.update');
    Route::delete('/{note}', [NoteController::class, 'destroy'])->name('notes.destroy');
    Route::post('/{note}/pin', [NoteController::class, 'togglePin'])->name('notes.pin');
    Route::post('/{note}/favorite', [NoteController::class, 'toggleFavorite'])->name('notes.favorite');
    Route::post('/{note}/duplicate', [NoteController::class, 'duplicate'])->name('notes.duplicate');
    Route::post('/{note}/assistant', [AiAssistantController::class, 'store'])->middleware('throttle:10,1')->name('notes.assistant');
    Route::get('/{note}/chunks', [NoteController::class, 'chunks'])->name('notes.chunks');
    Route::get('/{note}/chunks/verify', [NoteController::class, 'verifyChunks'])->name('notes.chunks.verify');
    Route::put('/{note}/chunks/{chunk}', [NoteController::class, 'updateChunk'])->name('notes.chunks.update');
    Route::delete('/{note}/chunks/{chunk}', [NoteController::class, 'deleteChunk'])->name('notes.chunks.delete');
    Route::post('/{note}/chunks/reorder', [NoteController::class, 'reorderChunks'])->name('notes.chunks.reorder');
    Route::get('/{note}/export/{format?}', [NoteController::class, 'export'])->name('notes.export');
});

Route::get('/api/player-stats', [NoteController::class, 'stats'])->name('api.stats');
