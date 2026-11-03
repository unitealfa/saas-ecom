<?php

use App\Models\Central\Subscriptions\Subscription;
use Illuminate\Foundation\Testing\TestCase;

pest()->extend(TestCase::class);

test('a transition state changes when the subscription or renewal changes', function (): void {
    $subscription = new Subscription(['status' => 3, 'period' => 1, 'auto_renew' => true]);
    $initial = $subscription->transitionState();
    $subscription->auto_renew = false;
    expect($subscription->transitionState())->not->toBe($initial);
});
