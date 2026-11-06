<?php

use App\Http\Controllers\NoteController;
use Illuminate\Support\Facades\Route;

Route::get('/', [NoteController::class, 'index'])->name('panel');

Route::prefix('api/notes')->group(function () {
    Route::get('/', [NoteController::class, 'index'])->name('notes.index');
    Route::post('/', [NoteController::class, 'store'])->name('notes.store');
    Route::post('/import', [NoteController::class, 'import'])->name('notes.import');
    Route::get('/{note}', [NoteController::class, 'show'])->name('notes.show');
    Route::put('/{note}', [NoteController::class, 'update'])->name('notes.update');
    Route::delete('/{note}', [NoteController::class, 'destroy'])->name('notes.destroy');
    Route::post('/{note}/pin', [NoteController::class, 'togglePin'])->name('notes.pin');
    Route::post('/{note}/favorite', [NoteController::class, 'toggleFavorite'])->name('notes.favorite');
    Route::post('/{note}/duplicate', [NoteController::class, 'duplicate'])->name('notes.duplicate');
    Route::get('/{note}/chunks', [NoteController::class, 'chunks'])->name('notes.chunks');
    Route::get('/{note}/chunks/verify', [NoteController::class, 'verifyChunks'])->name('notes.chunks.verify');
    Route::put('/{note}/chunks/{chunk}', [NoteController::class, 'updateChunk'])->name('notes.chunks.update');
    Route::delete('/{note}/chunks/{chunk}', [NoteController::class, 'deleteChunk'])->name('notes.chunks.delete');
    Route::post('/{note}/chunks/reorder', [NoteController::class, 'reorderChunks'])->name('notes.chunks.reorder');
    Route::get('/{note}/export/{format?}', [NoteController::class, 'export'])->name('notes.export');
});

Route::get('/api/player-stats', [NoteController::class, 'stats'])->name('api.stats');
