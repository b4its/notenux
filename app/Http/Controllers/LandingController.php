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

        return view('landing', [
            'noteCount' => max($noteCount, 12),
            'chunkCount' => max($chunkCount, 48),
            'user' => $request->user(),
        ]);
    }
}
