<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 1 identity extension: public handle, avatar, locale, status.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 32)->unique()->after('name');
            $table->string('avatar_path')->nullable()->after('password');
            $table->char('locale', 2)->default('fa')->after('avatar_path');
            $table->string('status', 16)->default('active')->index()->after('locale');
            $table->timestamp('last_login_at')->nullable()->after('remember_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['username', 'avatar_path', 'locale', 'status', 'last_login_at']);
        });
    }
};
