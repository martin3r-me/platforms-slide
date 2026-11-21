<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Wer hat das Deck zuletzt geändert und wann (für „aktualisiert von … vor …“ in den Live-Ansichten). */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('slides_decks')) {
            return;
        }
        if (! Schema::hasColumn('slides_decks', 'last_editor_user_id')) {
            Schema::table('slides_decks', function (Blueprint $table) {
                $table->unsignedBigInteger('last_editor_user_id')->nullable();
            });
        }
        if (! Schema::hasColumn('slides_decks', 'last_edited_at')) {
            Schema::table('slides_decks', function (Blueprint $table) {
                $table->timestamp('last_edited_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('slides_decks')) {
            return;
        }
        foreach (['last_editor_user_id', 'last_edited_at'] as $col) {
            if (Schema::hasColumn('slides_decks', $col)) {
                Schema::table('slides_decks', function (Blueprint $table) use ($col) {
                    $table->dropColumn($col);
                });
            }
        }
    }
};
