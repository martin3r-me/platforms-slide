<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slides_slides', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('presentation_id')->constrained('slides_presentations')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('layout_key', 100)->nullable(); // null = freeform
            $table->json('content')->nullable();
            $table->json('background')->nullable();
            $table->string('transition', 50)->nullable();
            $table->text('notes')->nullable(); // Speaker Notes
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->boolean('is_hidden')->default(false);
            $table->timestamps();

            $table->index(['presentation_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slides_slides');
    }
};
