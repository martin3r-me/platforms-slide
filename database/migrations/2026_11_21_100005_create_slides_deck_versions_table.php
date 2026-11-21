<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Versionen eines Decks: vollständiger Schnappschuss (Deck-Eckdaten, Theme, alle Folien) als JSON.
 * kind: auto (vor einer Änderungsrunde), manual (bewusst festgehalten), before_restore (Stand vor einer Wiederherstellung).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('slides_deck_versions')) {
            return;
        }

        Schema::create('slides_deck_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deck_id')->constrained('slides_decks')->cascadeOnDelete();
            $table->unsignedInteger('revision');
            $table->string('kind', 20)->default('auto');
            $table->string('label', 200)->nullable();
            $table->unsignedInteger('slide_count')->default(0);
            $table->char('content_hash', 40);
            $table->longText('snapshot');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['deck_id', 'id'], 'slides_deck_versions_deck_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slides_deck_versions');
    }
};
