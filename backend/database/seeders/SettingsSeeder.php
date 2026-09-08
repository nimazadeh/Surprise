<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Seed every known setting so `settings` is the single source of truth.
     * Values mirror config/shirin.php defaults and are editable at runtime.
     */
    public function run(): void
    {
        $rows = [
            ['key' => 'site.name', 'value' => config('app.name', 'SHIRIN'), 'type' => Setting::TYPE_STRING, 'group' => 'site'],
            ['key' => 'site.locale_default', 'value' => 'fa', 'type' => Setting::TYPE_STRING, 'group' => 'site'],

            ['key' => 'features.download_center', 'value' => 'off', 'type' => Setting::TYPE_STRING, 'group' => 'features'],
            ['key' => 'features.music_lab', 'value' => '0', 'type' => Setting::TYPE_BOOLEAN, 'group' => 'features'],
            ['key' => 'features.music_lab_premium_only', 'value' => '1', 'type' => Setting::TYPE_BOOLEAN, 'group' => 'features'],
            ['key' => 'features.ads', 'value' => '0', 'type' => Setting::TYPE_BOOLEAN, 'group' => 'features'],
            ['key' => 'features.user_uploads', 'value' => 'off', 'type' => Setting::TYPE_STRING, 'group' => 'features'],

            ['key' => 'seo.title_suffix', 'value' => 'SHIRIN', 'type' => Setting::TYPE_STRING, 'group' => 'seo'],
            ['key' => 'player.preview_limit_sec', 'value' => '30', 'type' => Setting::TYPE_INTEGER, 'group' => 'player'],
        ];

        foreach ($rows as $row) {
            Setting::query()->updateOrCreate(
                ['key' => $row['key']],
                ['value' => $row['value'], 'type' => $row['type'], 'group' => $row['group']]
            );
        }
    }
}
