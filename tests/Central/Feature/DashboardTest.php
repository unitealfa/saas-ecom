<?php

use App\Enums\VerificationStatusEnum;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('an approved merchant without a shop must complete first shop setup before the dashboard', function () {
    $reviewer = User::factory()->create();
    $user = User::factory()->create([
        'legal_verification_status' => VerificationStatusEnum::VERIFIED,
        'legal_profile_version' => 1, 'legal_verified_at' => now(), 'legal_verified_by_id' => $reviewer->id,
    ]);
    $this->actingAs($user, 'central');

    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('owner.first-shop'));
});
