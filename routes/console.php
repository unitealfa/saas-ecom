<?php

use App\Models\Central\Users\User;
use App\Services\Central\Billing\SaasDocumentDeliveryService;
use App\Services\Central\Subscriptions\DefaultSubscription;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('aydra:reconcile-saas-deliveries', function () {
    $count = app(
        SaasDocumentDeliveryService::class
    )->reconcileStale();

    $this->info($count.' envoi(s) à vérifier ou relancer.');
})->purpose('Réconcilier les envois SaaS bloqués.');

Artisan::command('aydra:resolve-subscriptions', function () {
    User::whereHas('subscriptions', fn ($query) => $query->dueForResolution())
        ->orderBy('id')->chunkById(100, function ($owners): void {
            foreach ($owners as $owner) {
                app(DefaultSubscription::class)->ensure($owner);
            }
        });
})->purpose('Actualiser les abonnements arrivés à échéance.');

Schedule::command('aydra:retry-tenant-provisioning --scheduled')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('aydra:schedule-tenant-migrations')->everyMinute()->withoutOverlapping()->onOneServer();

Schedule::command('aydra:reconcile-saas-deliveries')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('aydra:resolve-subscriptions')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
