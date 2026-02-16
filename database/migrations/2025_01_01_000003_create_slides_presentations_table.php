<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slides_presentations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('folder_id')->nullable()->constrained('slides_folders')->nullOnDelete();
            $table->json('theme')->nullable();
            $table->json('settings')->nullable();
            $table->unsignedInteger('slide_width')->default(1920);
            $table->unsignedInteger('slide_height')->default(1080);
            $table->boolean('is_published')->default(false);
            $table->string('public_token', 64)->nullable()->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'folder_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slides_presentations');
    }
};
