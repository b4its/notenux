<?php

use App\Models\User;
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
        Schema::table('notes', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->index(['user_id', 'updated_at']);
            $table->index(['user_id', 'category']);
            $table->index(['user_id', 'is_pinned']);
        });

        $firstUser = User::first();
        if ($firstUser) {
            DB::table('notes')->whereNull('user_id')->update(['user_id' => $firstUser->id]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropIndex(['user_id', 'updated_at']);
            $table->dropIndex(['user_id', 'category']);
            $table->dropIndex(['user_id', 'is_pinned']);
            $table->dropColumn('user_id');
        });
    }
};
