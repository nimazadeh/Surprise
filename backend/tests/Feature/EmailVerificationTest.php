<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_user_sees_notice(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get('/verify-email')->assertOk();
    }

    public function test_email_can_be_verified_with_signed_url(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $this->actingAs($user)->get($url)->assertRedirect('/');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }
}
