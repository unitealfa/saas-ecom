<?php

use App\Console\Commands\Central\LocalDockerWorkers;

test('the local compose definition keeps the three independent queues and one scheduler', function (): void {
    $compose = json_decode(file_get_contents(dirname(__DIR__, 3).'/deployment/docker/compose.yml'), true, flags: JSON_THROW_ON_ERROR);
    expect($compose['services']['provisioning']['command'])->toContain('central-provisioning', '--queue=tenant-provisioning', '--timeout=300')
        ->and($compose['services']['documents']['command'])->toContain('--queue=saas-documents', '--tries=1')
        ->and($compose['services']['account-mail']['command'])->toContain('--queue=default', '--tries=3')
        ->and($compose['services']['scheduler']['command'])->toContain('schedule:work');
    foreach (['provisioning', 'documents', 'account-mail', 'scheduler'] as $name) {
        expect($compose['services'][$name]['restart'])->toBe('unless-stopped')
            ->and($compose['services'][$name]['volumes'][0]['read_only'])->toBeTrue();
    }
    expect(class_exists(LocalDockerWorkers::class))->toBeTrue();
});

test('the dependency volume is isolated and workers probe before reporting readiness', function (): void {
    $compose = json_decode(file_get_contents(dirname(__DIR__, 3).'/deployment/docker/compose.yml'), true, flags: JSON_THROW_ON_ERROR);
    foreach ($compose['services'] as $name => $service) {
        $vendor = collect($service['volumes'])->firstWhere('source', 'vendor');
        expect($vendor['volume']['nocopy'])->toBeTrue();
        if ($name !== 'dependencies') {
            expect($service['entrypoint'][2])->toContain('aydra:docker-workers probe', 'touch /tmp/aydra-ready', 'exec "$@"');
        }
    }
});
