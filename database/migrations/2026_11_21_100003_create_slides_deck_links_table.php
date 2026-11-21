<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lose Kopplung eines Decks an beliebige Objekte (Morph-Alias), z. B. Gremium oder Sitzung im Management Book.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('slides_deck_links')) {
            return;
        }

        Schema::create('slides_deck_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deck_id')->constrained('slides_decks')->cascadeOnDelete();
            $table->string('linkable_type', 100);
            $table->unsignedBigInteger('linkable_id');
            $table->string('role', 50)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['deck_id', 'linkable_type', 'linkable_id'], 'slides_deck_links_unique');
            $table->index(['linkable_type', 'linkable_id'], 'slides_deck_links_linkable_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slides_deck_links');
    }
};
