<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Models\NoteChunk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class LandingController extends Controller
{
    /**
     * Display the interactive gamified landing page.
     */
    public function index(Request $request): View
    {
        [$noteCount, $chunkCount] = Cache::remember('notenux:landing_counts', 300, function (): array {
            return [
                Note::count(),
                NoteChunk::count(),
            ];
        });

        return view('landing', [
            'noteCount' => max($noteCount, 12),
            'chunkCount' => max($chunkCount, 48),
            'user' => $request->user(),
        ]);
    }
}
