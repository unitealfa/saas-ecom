<?php

use App\Console\Commands\Central\LocalProvisioningWorker;
use Illuminate\Foundation\Testing\TestCase;

pest()->extend(TestCase::class);

test('the local account mail worker has its own default queue and three tries', function (): void {
    $script = (new ReflectionMethod(LocalProvisioningWorker::class, 'accountMailWorkerScript'))->invoke(new LocalProvisioningWorker);
    expect($script)->toContain('queue:work database --queue=default', '--tries=3', '--timeout=60', '--max-time=3600', 'local-account-mail-worker.log')
        ->not->toContain('saas-documents', 'tenant-provisioning');
});
