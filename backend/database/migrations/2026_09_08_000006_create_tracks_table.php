<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catalogue tracks. `album_id` is nullable so loose singles can exist
     * without a parent album; `duration_sec` is an integer ready for
     * automatic probing later (no audio handled in this phase).
     */
    public function up(): void
    {
        Schema::create('tracks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('album_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('artist_id')->constrained()->cascadeOnDelete();
            $table->string('title', 255);
            $table->string('slug', 255)->unique();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('track_number')->nullable();
            $table->unsignedInteger('duration_sec')->nullable();
            $table->foreignId('genre_id')->nullable()->constrained()->nullOnDelete();
            $table->char('language', 2)->default('fa');
            $table->boolean('lyrics_available')->default(false);
            $table->string('status', 16)->default('draft');
            $table->string('source', 16)->default('owned');
            $table->string('provider_id', 255)->nullable();
            $table->string('seo_title', 255)->nullable();
            $table->string('seo_description', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['album_id', 'track_number']);
            $table->index('status');
            $table->index('source');
            $table->index('provider_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tracks');
    }
};
