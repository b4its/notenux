<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Clean up duplicate system categories only if categories already exist
        if (DB::table('categories')->count() > 0) {
            // If any category has is_system = true and user_id is set, convert it to is_system = false so it doesn't leak
            DB::table('categories')
                ->where('is_system', true)
                ->whereNotNull('user_id')
                ->update(['is_system' => false]);

            // Keep only one global system category per slug if user_id is null
            $systemSlugs = DB::table('categories')->whereNull('user_id')->pluck('slug')->unique();
            foreach ($systemSlugs as $slug) {
                $canonId = DB::table('categories')->whereNull('user_id')->where('slug', $slug)->orderBy('id', 'asc')->value('id');
                if ($canonId) {
                    DB::table('categories')->whereNull('user_id')->where('slug', $slug)->where('id', '!=', $canonId)->delete();
                }
            }
        }

        // 2. Re-assign user greeting notes to their actual users based on greeting content
        $greetingNotes = DB::table('notes')->where('title', 'like', '%Halo%Selamat Datang%')->get();
        foreach ($greetingNotes as $gn) {
            if (preg_match('/Halo <strong>(.*?)<\/strong>/', (string) $gn->content, $matches)) {
                $userName = trim($matches[1]);
                $matchingUserId = DB::table('users')->where('name', $userName)->value('id');
                if ($matchingUserId) {
                    DB::table('notes')->where('id', $gn->id)->update(['user_id' => $matchingUserId]);
                }
            }
        }

        // Ensure each user has at most one initial welcome note
        $users = DB::table('users')->get();
        foreach ($users as $u) {
            $userGreetings = DB::table('notes')
                ->where('user_id', $u->id)
                ->where('title', 'like', '%Halo%Selamat Datang%')
                ->orderBy('id', 'asc')
                ->pluck('id');
            if ($userGreetings->count() > 1) {
                $toDelete = $userGreetings->slice(1)->all();
                DB::table('note_chunks')->whereIn('note_id', $toDelete)->delete();
                DB::table('notes')->whereIn('id', $toDelete)->delete();
            }
        }

        // Purge duplicate dummy sample notes per user
        $dummyNotes = DB::table('notes')->where('title', 'like', 'Quest Scroll #%')->get();
        $seenTitlesPerUser = [];
        foreach ($dummyNotes as $dn) {
            $key = "{$dn->user_id}_{$dn->title}";
            if (isset($seenTitlesPerUser[$key])) {
                DB::table('note_chunks')->where('note_id', $dn->id)->delete();
                DB::table('notes')->where('id', $dn->id)->delete();
            } else {
                $seenTitlesPerUser[$key] = true;
            }
        }

        // Remove any orphan notes whose user does not exist or user_id is null
        $validUserIds = DB::table('users')->pluck('id')->all();
        if (! empty($validUserIds)) {
            $orphans = DB::table('notes')
                ->where(function ($q) use ($validUserIds) {
                    $q->whereNull('user_id')->orWhereNotIn('user_id', $validUserIds);
                })
                ->pluck('id');
            if ($orphans->isNotEmpty()) {
                DB::table('note_chunks')->whereIn('note_id', $orphans)->delete();
                DB::table('notes')->whereIn('id', $orphans)->delete();
            }
        }

        // 3. Make notes.user_id strictly NOT NULL
        Schema::table('notes', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
        });
    }
};
