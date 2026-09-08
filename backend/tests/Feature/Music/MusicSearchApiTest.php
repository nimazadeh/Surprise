<?php

namespace Tests\Feature\Music;

use App\Models\Album;
use App\Models\Artist;
use App\Models\Track;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MusicSearchApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Hermetic provider tests: enable the adapter config and fake every
     * outbound call — no test ever reaches the network.
     */
    protected function enableProvider(): void
    {
        config([
            'shirin.providers.deezer.enabled' => true,
            'shirin.providers.deezer.cache_ttl' => 60,
        ]);
    }

    public function test_search_returns_enveloped_grouped_results(): void
    {
        Artist::factory()->published()->create(['name' => 'Search Singer']);

        $this->getJson('/api/v1/search?q=Search')
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => ['query', 'type', 'artists', 'albums', 'tracks'],
                'message',
            ])
            ->assertJsonPath('data.query', 'Search')
            ->assertJsonPath('data.type', 'all')
            ->assertJsonPath('data.artists.0.name', 'Search Singer');
    }

    public function test_search_hides_drafts(): void
    {
        Artist::factory()->published()->create(['name' => 'Visible Artist']);
        Artist::factory()->create(['name' => 'Hidden Artist', 'status' => 'draft']);

        $response = $this->getJson('/api/v1/search?q=Artist')->assertOk();

        $names = array_column($response->json('data.artists'), 'name');

        $this->assertContains('Visible Artist', $names);
        $this->assertNotContains('Hidden Artist', $names);
    }

    public function test_search_by_exact_slug_matches(): void
    {
        Artist::factory()->published()->create(['name' => 'Slug Owner', 'slug' => 'exact-slug-artist']);

        $this->getJson('/api/v1/search?q=exact-slug-artist')
            ->assertOk()
            ->assertJsonPath('data.artists.0.slug', 'exact-slug-artist');
    }

    public function test_type_filter_restricts_results(): void
    {
        Artist::factory()->published()->create(['name' => 'Typed Singer']);
        Album::factory()->published()->create(['title' => 'Typed Album']);
        Track::factory()->published()->create(['title' => 'Typed Track']);

        $response = $this->getJson('/api/v1/search?q=Typed&type=track')->assertOk();

        $this->assertSame([], $response->json('data.artists'));
        $this->assertSame([], $response->json('data.albums'));
        $this->assertNotEmpty($response->json('data.tracks'));
    }

    public function test_like_wildcards_in_user_input_are_neutralized(): void
    {
        Artist::factory()->published()->create(['name' => 'Alpha Beta']);
        Artist::factory()->published()->create(['name' => '100% Love']);

        // '%%' would match everything with an unescaped LIKE; stripped, it
        // matches nothing.
        $this->json('GET', '/api/v1/search', ['q' => '%%'])
            ->assertOk()
            ->assertJsonPath('data.artists', []);

        // '100%' must find the literal "100% Love" without matching every row.
        $this->json('GET', '/api/v1/search', ['q' => '100%'])
            ->assertOk()
            ->assertJsonPath('data.artists.0.name', '100% Love');
    }

    public function test_provider_results_are_merged_owned_first(): void
    {
        $this->enableProvider();
        Artist::factory()->published()->create(['name' => 'Owned Artist']);
        Http::fake([
            '*/search/artist*' => Http::response(['data' => [
                ['id' => 42, 'name' => 'Provider Artist'],
            ]]),
            '*/search/album*' => Http::response(['data' => []]),
            '*/search/track*' => Http::response(['data' => []]),
        ]);

        $response = $this->getJson('/api/v1/search?q=Artist')->assertOk();

        $names = array_column($response->json('data.artists'), 'name');

        $this->assertSame(['Owned Artist', 'Provider Artist'], $names);
    }

    public function test_provider_outage_still_returns_owned_results(): void
    {
        $this->enableProvider();
        Artist::factory()->published()->create(['name' => 'Owned Artist']);
        Http::fake(fn () => Http::response([], 500));

        $this->getJson('/api/v1/search?q=Owned')
            ->assertOk()
            ->assertJsonPath('data.artists.0.name', 'Owned Artist');
    }

    public function test_short_query_is_rejected_with_envelope(): void
    {
        $this->getJson('/api/v1/search?q=x')
            ->assertStatus(422)
            ->assertJson(['success' => false, 'code' => 'VALIDATION']);
    }

    public function test_limit_above_max_is_rejected(): void
    {
        $this->getJson('/api/v1/search?q=ab&limit=99')
            ->assertStatus(422)
            ->assertJson(['success' => false, 'code' => 'VALIDATION']);
    }

    public function test_search_is_rate_limited(): void
    {
        $response = null;

        for ($i = 0; $i < 31; $i++) {
            $response = $this->getJson('/api/v1/search?q=ab');
        }

        $response?->assertStatus(429)
            ->assertJson(['success' => false, 'code' => 'RATE_LIMITED']);
    }
}
