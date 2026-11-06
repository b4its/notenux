<?php

use App\Http\Controllers\NoteController;
use Illuminate\Support\Facades\Route;

Route::get('/', [NoteController::class, 'index'])->name('panel');

Route::prefix('api/notes')->group(function () {
    Route::get('/', [NoteController::class, 'index'])->name('notes.index');
    Route::post('/', [NoteController::class, 'store'])->name('notes.store');
    Route::get('/{note}', [NoteController::class, 'show'])->name('notes.show');
    Route::put('/{note}', [NoteController::class, 'update'])->name('notes.update');
    Route::delete('/{note}', [NoteController::class, 'destroy'])->name('notes.destroy');
    Route::post('/{note}/pin', [NoteController::class, 'togglePin'])->name('notes.pin');
    Route::get('/{note}/chunks', [NoteController::class, 'chunks'])->name('notes.chunks');
});

Route::get('/api/player-stats', [NoteController::class, 'stats'])->name('api.stats');
