<?php

namespace Tests\Feature\Music;

use App\Contracts\MusicProvider;
use App\Models\Artist;
use App\Models\Setting;
use App\Services\CatalogueService;
use App\Services\SettingsService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Fixtures\FakeMusicProvider;
use Tests\TestCase;

class CatalogueServiceTest extends TestCase
{
    use RefreshDatabase;

    protected FakeMusicProvider $fake;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->fake = new FakeMusicProvider;
        $this->app->instance(MusicProvider::class, $this->fake);
    }

    protected function service(): CatalogueService
    {
        return app(CatalogueService::class);
    }

    public function test_owned_results_come_first_and_provider_items_are_appended(): void
    {
        Artist::factory()->published()->create(['name' => 'Owned Singer']);
        $this->fake->searchArtistResults = [
            ['id' => '7312776', 'name' => 'Provider Singer', 'source' => 'deezer', 'provider_id' => '7312776'],
        ];

        $results = $this->service()->search('singer', 'artist', 10);

        $this->assertCount(2, $results['artists']);
        $this->assertSame('Owned Singer', $results['artists'][0]['name']);
        $this->assertSame('owned', $results['artists'][0]['source']);
        $this->assertSame('Provider Singer', $results['artists'][1]['name']);
        $this->assertSame('deezer', $results['artists'][1]['source']);
    }

    public function test_owned_twin_suppresses_its_provider_item(): void
    {
        Artist::factory()->published()->create([
            'name' => 'Owned Twin',
            'source' => 'deezer',
            'provider_id' => '7312776',
        ]);
        $this->fake->searchArtistResults = [
            ['id' => '7312776', 'name' => 'Owned Twin (provider)', 'source' => 'deezer', 'provider_id' => '7312776'],
            ['id' => '999', 'name' => 'Someone Else', 'source' => 'deezer', 'provider_id' => '999'],
        ];

        $results = $this->service()->search('twin', 'artist', 10);

        $names = array_column($results['artists'], 'name');

        $this->assertContains('Owned Twin', $names);
        $this->assertContains('Someone Else', $names);
        $this->assertNotContains('Owned Twin (provider)', $names);
    }

    public function test_draft_twin_also_suppresses_the_provider_item(): void
    {
        Artist::factory()->create([
            'name' => 'Draft Twin',
            'status' => 'draft',
            'source' => 'deezer',
            'provider_id' => '7312776',
        ]);
        $this->fake->searchArtistResults = [
            ['id' => '7312776', 'name' => 'Draft Twin (provider)', 'source' => 'deezer', 'provider_id' => '7312776'],
        ];

        $results = $this->service()->search('twin', 'artist', 10);

        $this->assertSame([], $results['artists']);
    }

    public function test_flag_off_never_calls_the_provider(): void
    {
        app(SettingsService::class)->set('features.catalogue_provider', false, Setting::TYPE_BOOLEAN, 'features');
        Artist::factory()->published()->create(['name' => 'Owned Singer']);
        $this->fake->searchArtistResults = [
            ['id' => '7312776', 'name' => 'Provider Singer', 'source' => 'deezer', 'provider_id' => '7312776'],
        ];

        $results = $this->service()->search('singer', 'all', 10);

        $this->assertCount(1, $results['artists']);
        $this->assertSame(0, $this->fake->searchCalls);
        $this->assertSame(0, $this->fake->getCalls);
    }

    public function test_provider_outage_degrades_to_owned_only(): void
    {
        Artist::factory()->published()->create(['name' => 'Owned Singer']);
        $this->fake->failing = true;

        $results = $this->service()->search('singer', 'all', 10);

        $this->assertCount(1, $results['artists']);
        $this->assertSame('Owned Singer', $results['artists'][0]['name']);
    }

    public function test_search_type_filters_the_groups_queried(): void
    {
        $this->fake->searchArtistResults = [
            ['id' => '7312776', 'name' => 'Provider Singer', 'source' => 'deezer', 'provider_id' => '7312776'],
        ];

        $results = $this->service()->search('singer', 'album', 10);

        $this->assertSame([], $results['artists']);
        $this->assertSame([], $results['tracks']);
        $this->assertSame(0, $this->fake->searchCalls);
    }
}
