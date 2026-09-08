<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_locale_is_persian_rtl(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<html lang="fa" dir="rtl">', false);
    }

    public function test_locale_can_switch_to_english_ltr(): void
    {
        $this->get('/locale/en')
            ->assertRedirect('/')
            ->assertSessionHas('locale', 'en');

        $this->withSession(['locale' => 'en'])->get('/')
            ->assertSee('<html lang="en" dir="ltr">', false);
    }

    public function test_locale_switch_persists_to_user_profile(): void
    {
        $user = User::factory()->create(['locale' => 'fa']);

        $this->actingAs($user)->get('/locale/en');

        $this->assertSame('en', $user->fresh()->locale);
    }

    public function test_unsupported_locale_returns_404(): void
    {
        $this->get('/locale/de')->assertNotFound();
    }
}
