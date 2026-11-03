<?php

use App\Enums\Central\Subscriptions\PeriodEnum;
use App\Enums\Central\Subscriptions\StatusEnum;

test('subscription states and billing periods have distinct readable labels', function (): void {
    expect(array_map(fn (StatusEnum $status) => $status->label(), StatusEnum::cases()))
        ->toBe(['Prévu', 'Essai', 'Actif', 'Expiré', 'Annulé']);
    expect(PeriodEnum::MONTHLY->label())->toBe('Mensuelle')
        ->and(PeriodEnum::YEARLY->label())->toBe('Annuelle');
});
