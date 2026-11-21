<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Decks (Präsentationen) im HTML-Folienformat. theme_css gilt für alle Folien des Decks,
 * revision steigt bei jeder Änderung an Deck oder Folie (Live-Aktualisierung).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('slides_decks')) {
            return;
        }

        Schema::create('slides_decks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('size', 8)->default('16:9');
            $table->longText('theme_css')->nullable();
            $table->json('fonts')->nullable();
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('revision')->default(1);
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'status'], 'slides_decks_team_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slides_decks');
    }
};
