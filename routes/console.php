<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('aydra:reconcile-saas-deliveries', function () {
    $count = app(
        \App\Services\Central\Billing\SaasDocumentDeliveryService::class
    )->reconcileStale();

    $this->info($count.' envoi(s) à vérifier ou relancer.');
})->purpose('Réconcilier les envois SaaS bloqués.');

Schedule::command('aydra:retry-tenant-provisioning --scheduled')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('aydra:reconcile-saas-deliveries')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();
