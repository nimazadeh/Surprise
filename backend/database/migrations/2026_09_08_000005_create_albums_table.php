<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Albums, singles, EPs and compilations. One owning artist; tracks on
     * compilations keep their own artist_id for correct attribution.
     */
    public function up(): void
    {
        Schema::create('albums', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artist_id')->constrained()->cascadeOnDelete();
            $table->string('title', 255);
            $table->string('slug', 255)->unique();
            $table->text('description')->nullable();
            $table->string('cover', 512)->nullable();
            $table->date('release_date')->nullable();
            $table->unsignedSmallInteger('release_year')->nullable();
            $table->string('type', 16)->default('album');
            $table->string('status', 16)->default('draft');
            $table->string('source', 16)->default('owned');
            $table->string('provider_id', 255)->nullable();
            $table->string('seo_title', 255)->nullable();
            $table->string('seo_description', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('type');
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
        Schema::dropIfExists('albums');
    }
};
