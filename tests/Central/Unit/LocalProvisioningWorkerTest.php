<?php

use App\Console\Commands\Central\LocalProvisioningWorker;
use Illuminate\Foundation\Testing\TestCase;
use Symfony\Component\Process\Process;

pest()->extend(TestCase::class);

test('the Windows wrapper restarts a failed listener and logs its exit without consuming jobs', function (): void {
    if (PHP_OS_FAMILY !== 'Windows') {
        $this->markTestSkipped('Windows supervision.');
    }

    $directory = sys_get_temp_dir().'/aydra-worker-'.bin2hex(random_bytes(8));
    mkdir($directory);
    $fake = $directory.'/listener.cmd';
    $log = $directory.'/worker.log';
    file_put_contents($fake, "@echo off\r\necho simulated-output\r\necho simulated-error 1>&2\r\nexit /b 1\r\n");

    try {
        $script = new ReflectionMethod(LocalProvisioningWorker::class, 'workerScript')
            ->invoke(new LocalProvisioningWorker);
        expect($script)->toContain('--timeout=300', 'Start-Sleep -Seconds 60', 'while ($true)');

        $script = str_replace(storage_path('logs/local-provisioning-worker.log'), $log, $script);
        $script = preg_replace("/& '[^']+' '[^']+' queue:listen .*? \*>>/", "& '$fake' *>>", $script);
        $script = '$attempt = 0; '.str_replace('Start-Sleep -Seconds 60', 'if (++$attempt -eq 2) { break }', $script);
        expect($script)->not->toContain('queue:listen');

        $process = new Process([
            getenv('SystemRoot').'/System32/WindowsPowerShell/v1.0/powershell.exe',
            '-NoProfile', '-NonInteractive', '-EncodedCommand',
            base64_encode(mb_convert_encoding($script, 'UTF-16LE', 'UTF-8')),
        ]);
        $process->setTimeout(15);
        $process->mustRun();
        $output = mb_convert_encoding(file_get_contents($log), 'UTF-8', 'UTF-16LE');

        expect(substr_count($output, 'Listener stopped; exit code: 1'))->toBe(2)
            ->and(substr_count($output, 'simulated-output'))->toBe(2)
            ->and($output)->toContain('simulated-error');
    } finally {
        foreach (glob($directory.'/*') as $file) {
            unlink($file);
        }
        rmdir($directory);
    }
});
