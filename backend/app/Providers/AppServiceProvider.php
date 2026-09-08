<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // OWNER: unlimited, role-based, no subscription checks. Protection
        // against demotion/deletion is enforced in Phase 4 user management.
        Gate::before(function (User $user, string $ability): ?bool {
            return $user->hasRole('owner') ? true : null;
        });

        Gate::define('admin.access', fn (User $user): bool => $user->can('admin.access'));
    }
}
