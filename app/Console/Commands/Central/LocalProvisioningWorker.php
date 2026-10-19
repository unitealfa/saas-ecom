<?php

namespace App\Console\Commands\Central;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class LocalProvisioningWorker extends Command
{
    protected $signature = 'aydra:local-queue {action=status : install, start, stop or status}';

    protected $description = 'Gérer le worker local Aydra avec le Planificateur de tâches Windows.';

    public function handle(): int
    {
        $action = $this->argument('action');
        if (PHP_OS_FAMILY !== 'Windows' || ! app()->environment('local') || tenancy()->initialized
            || PHP_MAJOR_VERSION !== 8 || PHP_MINOR_VERSION !== 5) {
            $this->error('Cette commande exige Windows, PHP 8.5 et le contexte central local.');
            return self::FAILURE;
        }
        if (! in_array($action, ['install', 'start', 'stop', 'status'], true)) {
            $this->error('Action attendue : install, start, stop ou status.');
            return self::FAILURE;
        }
        $root = base_path();
        $name = 'Aydra-Provisioning-'.substr(hash('sha256', strtolower($root)), 0, 12);
        $description = 'Aydra provisioning: '.$root;
        $powershell = getenv('SystemRoot').'\\System32\\WindowsPowerShell\\v1.0\\powershell.exe';
        $worker = '$ErrorActionPreference = \'Stop\'; & '.$this->literal(PHP_BINARY).' '.$this->literal(base_path('artisan'))
            .' queue:listen central-provisioning --queue=tenant-provisioning --sleep=2 --tries=3 --timeout=300 --no-interaction'
            .' *>> '.$this->literal(storage_path('logs/local-provisioning-worker.log')).'; if ($LASTEXITCODE -eq 0) { exit 1 }; exit $LASTEXITCODE';
        $workerArguments = '-NoProfile -NonInteractive -WindowStyle Hidden -Command "'.str_replace('"', '\\"', $worker).'"';
        $script = '$ErrorActionPreference = \'Stop\'; $name = '.$this->literal($name).'; $description = '.$this->literal($description).';'
            .' $task = Get-ScheduledTask -TaskName $name -ErrorAction SilentlyContinue;'
            .' if ($task -and $task.Description -ne $description) { throw \'Task ownership mismatch.\' };';
        if (in_array($action, ['install', 'start'], true)) {
            $script .= ' $jobAction = New-ScheduledTaskAction -Execute '.$this->literal($powershell)
                .' -Argument '.$this->literal($workerArguments).' -WorkingDirectory '.$this->literal($root).';'
                .' if (-not $task) {'
                .' $owner = [System.Security.Principal.WindowsIdentity]::GetCurrent().Name;'
                .' $trigger = New-ScheduledTaskTrigger -AtLogOn -User $owner;'
                .' $principal = New-ScheduledTaskPrincipal -UserId $owner -LogonType Interactive -RunLevel Limited;'
                .' $settings = New-ScheduledTaskSettingsSet -Disable -Hidden -RestartCount 999 -RestartInterval (New-TimeSpan -Minutes 1)'
                .' -ExecutionTimeLimit ([TimeSpan]::Zero) -MultipleInstances IgnoreNew -StartWhenAvailable -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries;'
                .' Register-ScheduledTask -TaskName $name -Description $description -Action $jobAction -Trigger $trigger -Principal $principal -Settings $settings | Out-Null; }'
                .' else { Set-ScheduledTask -TaskName $name -Action $jobAction | Out-Null; };';
        }
        if ($action === 'start') {
            $script .= ' Enable-ScheduledTask -TaskName $name | Out-Null; Start-ScheduledTask -TaskName $name;';
        } elseif ($action === 'stop') {
            $script .= ' if ($task) { Disable-ScheduledTask -TaskName $name | Out-Null; Stop-ScheduledTask -TaskName $name; };';
        }
        $script .= ' $task = Get-ScheduledTask -TaskName $name -ErrorAction SilentlyContinue;'
            .' if ($task) { $info = Get-ScheduledTaskInfo -TaskName $name;'
            .' [PSCustomObject]@{Name=$task.TaskName; State=[string]$task.State; Enabled=$task.Settings.Enabled; LastResult=$info.LastTaskResult} | ConvertTo-Json -Compress }'
            .' else { Write-Output \'Worker task is not installed.\' };';
        $process = new Process([$powershell, '-NoProfile', '-NonInteractive', '-Command', $script], $root);
        $process->setTimeout(30);
        $process->run();
        if (! $process->isSuccessful()) {
            $this->error($process->getErrorOutput() ?: $process->getOutput());
            return self::FAILURE;
        }
        $this->line(trim($process->getOutput()));
        return self::SUCCESS;
    }

    private function literal(string $value): string
    {
        return "'".str_replace("'", "''", $value)."'";
    }
}
