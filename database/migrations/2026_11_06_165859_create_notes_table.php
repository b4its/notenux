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
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('title');
            $table->string('category')->default('quest');
            $table->longText('content')->nullable();
            $table->string('font_family')->default('pixel');
            $table->string('font_size')->default('16px');
            $table->string('theme_color')->default('blue');
            $table->unsignedInteger('word_count')->default(0);
            $table->unsignedInteger('char_count')->default(0);
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_favorite')->default(false);
            $table->unsignedInteger('level')->default(1);
            $table->unsignedInteger('xp')->default(10);
            $table->timestamps();

            $table->index(['category', 'updated_at']);
            $table->index('is_pinned');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
