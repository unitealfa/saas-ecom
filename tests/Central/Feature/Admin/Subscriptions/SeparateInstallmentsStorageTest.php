<?php

use App\Enums\Central\Subscriptions\InstallmentStatusEnum;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\Central\Subscriptions\SubscriptionInstallment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** Ce test suppose le nouveau schéma central migré dans la BDD de test isolée. */
test('subscriptions and installments live in two distinct tables with stable parent links', function (): void {
    expect(Schema::hasTable('subscriptions'))->toBeTrue()
        ->and(Schema::hasTable('subscription_installments'))->toBeTrue()
        ->and(Schema::hasColumn('subscriptions', 'record_type'))->toBeFalse()
        ->and(Schema::hasColumn('subscriptions', 'parent_subscription_id'))->toBeFalse()
        ->and(Schema::hasColumn('subscription_installments', 'subscription_id'))->toBeTrue();

    $owner = User::factory()->create();
    $start = now()->startOfSecond();
    $end = $start->copy()->addMonth();
    $parent = Subscription::factory()->create([
        'user_id' => $owner->id,
        'period_starts_at' => $start,
        'period_ends_at' => $end,
        'agreed_amount' => '12000.00',
    ]);

    $due = new SubscriptionInstallment;
    $due->forceFill([
        'user_id' => $owner->id,
        'subscription_id' => $parent->id,
        'installment_number' => 'ECH-'.Str::uuid(),
        'operation_key' => 'installment:test:'.Str::uuid(),
        'installment_amount' => '12000.00',
        'installment_status' => InstallmentStatusEnum::PENDING,
        'period_starts_at' => $start,
        'period_ends_at' => $end,
        'due_at' => $start,
    ])->save();

    expect($due->subscription->is($parent))->toBeTrue()
        ->and($due->matchesFullPeriod($parent))->toBeTrue()
        ->and($parent->installments()->sole()->is($due))->toBeTrue()
        ->and(DB::table('subscriptions')->where('id', $parent->id)->count())->toBe(1)
        ->and(DB::table('subscription_installments')->where('subscription_id', $parent->id)->count())->toBe(1);
});

test('an installment cannot belong to a different merchant than its subscription', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $start = now()->startOfSecond();
    $end = $start->copy()->addMonth();
    $parent = Subscription::factory()->create([
        'user_id' => $owner->id, 'period_starts_at' => $start,
        'period_ends_at' => $end,
    ]);

    expect(fn () => DB::table('subscription_installments')->insert([
        'uuid' => (string) Str::uuid(),
        'user_id' => $other->id,
        'subscription_id' => $parent->id,
        'installment_number' => 'ECH-'.Str::uuid(),
        'operation_key' => 'installment:test:'.Str::uuid(),
        'installment_amount' => '0.00',
        'installment_status' => InstallmentStatusEnum::PENDING->value,
        'period_starts_at' => $start,
        'period_ends_at' => $end,
        'due_at' => $start,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});
