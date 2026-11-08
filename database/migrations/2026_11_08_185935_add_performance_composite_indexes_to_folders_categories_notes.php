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
        Schema::table('folders', function (Blueprint $table) {
            $table->index(['user_id', 'name'], 'folders_user_id_name_index');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->index(['is_system', 'user_id', 'name'], 'categories_is_system_user_name_index');
        });

        Schema::table('notes', function (Blueprint $table) {
            $table->index(['user_id', 'is_pinned', 'word_count'], 'notes_user_pinned_words_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('folders', function (Blueprint $table) {
            $table->dropIndex('folders_user_id_name_index');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex('categories_is_system_user_name_index');
        });

        Schema::table('notes', function (Blueprint $table) {
            $table->dropIndex('notes_user_pinned_words_index');
        });
    }
};
