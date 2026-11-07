<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Models\NoteChunk;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LandingController extends Controller
{
    /**
     * Display the interactive gamified landing page.
     */
    public function index(Request $request): View
    {
        $noteCount = Note::count();
        $chunkCount = NoteChunk::count();
        $sampleNotes = Note::latest()->take(3)->get(['id', 'uuid', 'title', 'category', 'word_count', 'level']);

        return view('landing', [
            'noteCount' => max($noteCount, 12),
            'chunkCount' => max($chunkCount, 48),
            'sampleNotes' => $sampleNotes,
            'user' => $request->user(),
        ]);
    }
}
