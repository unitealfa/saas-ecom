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
        $workerArguments = '-NoProfile -NonInteractive -WindowStyle Hidden -EncodedCommand '
            .base64_encode(mb_convert_encoding($this->workerScript(), 'UTF-16LE', 'UTF-8'));
        $script = '$ErrorActionPreference = \'Stop\'; $name = '.$this->literal($name).'; $description = '.$this->literal($description).';'
            .' $task = Get-ScheduledTask -TaskName $name -ErrorAction SilentlyContinue;'
            .' if ($task -and $task.Description -ne $description) { throw \'Task ownership mismatch.\' };';
        if (in_array($action, ['install', 'start'], true)) {
            $script .= ' $jobAction = New-ScheduledTaskAction -Execute '.$this->literal($powershell)
                .' -Argument '.$this->literal($workerArguments).' -WorkingDirectory '.$this->literal($root).';'
                .' $owner = [System.Security.Principal.WindowsIdentity]::GetCurrent().Name;'
                .' $triggers = @((New-ScheduledTaskTrigger -AtLogOn -User $owner),'
                .' (New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 5)));'
                .' $settings = New-ScheduledTaskSettingsSet -Hidden -RestartCount 999 -RestartInterval (New-TimeSpan -Minutes 1)'
                .' -ExecutionTimeLimit ([TimeSpan]::Zero) -MultipleInstances IgnoreNew -StartWhenAvailable -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries;'
                .' if (-not $task) {'
                .' $principal = New-ScheduledTaskPrincipal -UserId $owner -LogonType Interactive -RunLevel Limited;'
                .' $settings.Enabled = $false;'
                .' Register-ScheduledTask -TaskName $name -Description $description -Action $jobAction -Trigger $triggers -Principal $principal -Settings $settings | Out-Null; }'
                .' else { $settings.Enabled = $task.Settings.Enabled;'
                .' Set-ScheduledTask -TaskName $name -Action $jobAction -Trigger $triggers -Settings $settings | Out-Null; };';
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

    private function workerScript(): string
    {
        $log = $this->literal(storage_path('logs/local-provisioning-worker.log'));

        return '$ErrorActionPreference = \'Continue\'; while ($true) { try { & '
            .$this->literal(PHP_BINARY).' '.$this->literal(base_path('artisan'))
            .' queue:listen central-provisioning --queue=tenant-provisioning --sleep=2 --tries=3 --timeout=300 --no-interaction'
            .' *>> '.$log.'; Add-Content -LiteralPath '.$log
            .' -Value ((Get-Date -Format o) + " Listener stopped; exit code: " + $LASTEXITCODE)'
            .'; } catch { Add-Content -LiteralPath '.$log
            .' -Value ((Get-Date -Format o) + " Worker wrapper failed: " + $_.Exception.GetType().Name)'
            .'; }; Start-Sleep -Seconds 60; }';
    }
}
