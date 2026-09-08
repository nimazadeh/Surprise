<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Forced slug renames (C-01). Public show routes resolve exact slug
     * first, then this table (301), then 404. Slugs are otherwise
     * immutable after publish and never reused (C-03).
     */
    public function up(): void
    {
        Schema::create('slug_redirects', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 16);
            $table->string('old_slug', 255);
            $table->string('new_slug', 255);
            $table->dateTime('created_at')->useCurrent();

            $table->unique(['subject_type', 'old_slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('slug_redirects');
    }
};
