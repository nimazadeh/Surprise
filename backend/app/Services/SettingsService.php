<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SettingsService
{
    /**
     * Read a setting with a forever-cache. Flags and site options change
     * rarely and are read on nearly every request — never query per hit.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $cached = Cache::rememberForever($this->cacheKey($key), function () use ($key) {
            return Setting::query()->find($key)?->decodedValue();
        });

        return $cached ?? $default;
    }

    public function set(string $key, mixed $value, string $type = Setting::TYPE_STRING, ?string $group = null): void
    {
        DB::transaction(function () use ($key, $value, $type, $group): void {
            Setting::query()->updateOrCreate(
                ['key' => $key],
                [
                    'value' => Setting::encodeValue($value, $type),
                    'type' => $type,
                    'group' => $group,
                ]
            );
        });

        Cache::forget($this->cacheKey($key));
    }

    public function forget(string $key): void
    {
        Setting::query()->whereKey($key)->delete();
        Cache::forget($this->cacheKey($key));
    }

    public function flushCache(?string $key = null): void
    {
        if ($key) {
            Cache::forget($this->cacheKey($key));

            return;
        }

        foreach (Setting::query()->pluck('key') as $stored) {
            Cache::forget($this->cacheKey($stored));
        }
    }

    protected function cacheKey(string $key): string
    {
        return 'settings.'.$key;
    }
}
