<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bilder für Folien. Ausgeliefert über eine nicht erratbare Token-URL (ohne Login),
 * damit sie in Folien-iframes, Präsentation, Druck und serverseitigem PDF gleichermaßen laden.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('slides_assets')) {
            return;
        }

        Schema::create('slides_assets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('token', 48)->unique();
            $table->unsignedBigInteger('team_id');
            $table->foreignId('deck_id')->nullable()->constrained('slides_decks')->nullOnDelete();
            $table->string('disk', 50);
            $table->string('path', 500);
            $table->string('original_name', 255)->nullable();
            $table->string('mime_type', 100);
            $table->string('extension', 10);
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('alt', 300)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'deck_id'], 'slides_assets_team_deck_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slides_assets');
    }
};
