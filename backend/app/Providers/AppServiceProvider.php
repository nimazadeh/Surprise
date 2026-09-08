<?php

namespace App\Providers;

use App\Contracts\CoverArtService;
use App\Models\User;
use App\Services\LocalCoverArtService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CoverArtService::class, LocalCoverArtService::class);
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

        // Direct permission check on purpose: calling $user->can() here would
        // re-enter this same gate and recurse for users lacking the permission.
        // checkPermissionTo() fails closed (false) when the row is missing.
        Gate::define('admin.access', fn (User $user): bool => $user->checkPermissionTo('admin.access'));
    }
}
