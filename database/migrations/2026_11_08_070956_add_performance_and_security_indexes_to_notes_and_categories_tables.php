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
            $table->index(['user_id', 'is_favorite'], 'notes_user_id_is_favorite_index');
            $table->index(['user_id', 'created_at'], 'notes_user_id_created_at_index');
            $table->index(['user_id', 'title'], 'notes_user_id_title_index');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->index(['user_id', 'name'], 'categories_user_id_name_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->dropIndex('notes_user_id_is_favorite_index');
            $table->dropIndex('notes_user_id_created_at_index');
            $table->dropIndex('notes_user_id_title_index');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex('categories_user_id_name_index');
        });
    }
};
