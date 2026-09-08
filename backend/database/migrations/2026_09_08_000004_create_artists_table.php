<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Owned catalogue artists. `source`/`provider_id` keep the dual-source
     * model (owned|deezer) open without a later backfill.
     */
    public function up(): void
    {
        Schema::create('artists', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('slug', 255)->unique();
            $table->text('bio')->nullable();
            $table->string('image', 512)->nullable();
            $table->string('country', 100)->nullable();
            $table->char('language', 2)->default('fa');
            $table->boolean('is_featured')->default(false);
            $table->string('status', 16)->default('draft');
            $table->string('source', 16)->default('owned');
            $table->string('provider_id', 255)->nullable();
            $table->string('seo_title', 255)->nullable();
            $table->string('seo_description', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('is_featured');
            $table->index('source');
            $table->index('provider_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('artists');
    }
};
