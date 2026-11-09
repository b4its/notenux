<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->index(['user_id', 'is_pinned', 'level'], 'notes_user_pinned_level_index');
            $table->index(['user_id', 'is_favorite', 'is_pinned', 'updated_at'], 'notes_user_fav_pinned_updated_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->dropIndex('notes_user_pinned_level_index');
            $table->dropIndex('notes_user_fav_pinned_updated_index');
        });
    }
};
