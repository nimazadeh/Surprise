<?php

namespace App\Services;

use App\Models\User;

/**
 * Single decision point for every feature flag. Controllers, gates and Blade
 * must call this service — never read `settings` keys directly.
 */
class FeatureFlagService
{
    public const MODE_OFF = 'off';

    public const MODE_PUBLIC = 'public';

    public const MODE_REGISTERED = 'registered';

    public const MODE_PREMIUM = 'premium';

    public function __construct(protected SettingsService $settings) {}

    public function downloadCenterMode(): string
    {
        return (string) $this->settings->get(
            'features.download_center',
            config('shirin.features.download_center', self::MODE_OFF)
        );
    }

    public function downloadCenterVisibleTo(?User $user): bool
    {
        return match ($this->downloadCenterMode()) {
            self::MODE_PUBLIC => true,
            self::MODE_REGISTERED => $user !== null,
            self::MODE_PREMIUM => $this->hasPremiumAccess($user),
            default => false,
        };
    }

    public function musicLabEnabled(): bool
    {
        return (bool) $this->settings->get(
            'features.music_lab',
            config('shirin.features.music_lab', false)
        );
    }

    public function musicLabAvailableTo(?User $user): bool
    {
        if (! $this->musicLabEnabled()) {
            return false;
        }

        $premiumOnly = (bool) $this->settings->get(
            'features.music_lab_premium_only',
            config('shirin.features.music_lab_premium_only', true)
        );

        return $premiumOnly ? $this->hasPremiumAccess($user) : $user !== null;
    }

    public function adsEnabled(): bool
    {
        return (bool) $this->settings->get(
            'features.ads',
            config('shirin.features.ads', false)
        );
    }

    /**
     * Dual-source catalogue: external provider enrichment (metadata only).
     * Kill switch — off means /api/v1 serves owned rows only and the
     * provider is never called.
     */
    public function catalogueProviderEnabled(): bool
    {
        return (bool) $this->settings->get(
            'features.catalogue_provider',
            config('shirin.features.catalogue_provider', true)
        );
    }

    public function uploadsAllowedFor(?User $user): bool
    {
        $mode = (string) $this->settings->get(
            'features.user_uploads',
            config('shirin.features.user_uploads', self::MODE_OFF)
        );

        return match ($mode) {
            self::MODE_REGISTERED => $user !== null,
            self::MODE_PREMIUM => $this->hasPremiumAccess($user),
            default => false,
        };
    }

    /**
     * Public-safe subset for clients (never leak internal flag names).
     *
     * @return array<string, mixed>
     */
    public function publicFlags(?User $user = null): array
    {
        return [
            'downloads' => $this->downloadCenterVisibleTo($user),
            'music_lab' => $this->musicLabAvailableTo($user),
            'ads' => $this->adsEnabled(),
            'uploads' => $this->uploadsAllowedFor($user),
            'catalogue_provider' => $this->catalogueProviderEnabled(),
        ];
    }

    /**
     * Premium resolution lives in exactly one place. Time-boxed grants
     * (`premium_grants` table) extend this method in Phase 3.
     */
    public function hasPremiumAccess(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasRole(['owner', 'premium_user']);
    }
}
