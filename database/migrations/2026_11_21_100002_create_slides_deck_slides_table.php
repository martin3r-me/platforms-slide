<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Folien eines Decks: genau ein <section> (bereinigtes HTML) je Folie, adressiert über slide_key.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('slides_deck_slides')) {
            return;
        }

        Schema::create('slides_deck_slides', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('deck_id')->constrained('slides_decks')->cascadeOnDelete();
            $table->string('slide_key', 64);
            $table->unsignedInteger('position')->default(0);
            $table->longText('html');
            $table->text('notes')->nullable();
            $table->boolean('is_hidden')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['deck_id', 'position'], 'slides_deck_slides_deck_pos_idx');
            $table->index(['deck_id', 'slide_key'], 'slides_deck_slides_deck_key_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slides_deck_slides');
    }
};
