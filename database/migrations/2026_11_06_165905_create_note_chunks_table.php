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
        Schema::create('note_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('note_id')->constrained('notes')->cascadeOnDelete();
            $table->string('chunk_uid', 64)->unique();
            $table->unsignedInteger('chunk_index')->default(0);
            $table->string('chunk_type', 32)->default('paragraph');
            $table->text('content');
            $table->unsignedInteger('byte_size')->default(0);
            $table->string('checksum', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['note_id', 'chunk_index']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('note_chunks');
    }
};
