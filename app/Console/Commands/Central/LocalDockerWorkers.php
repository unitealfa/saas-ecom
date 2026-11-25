<?php

namespace App\Console\Commands\Central;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

final class LocalDockerWorkers extends Command
{
    protected $signature = 'aydra:docker-workers {action=status : auto-start, dev-start, start, check, status, stop, restart, logs or probe}';

    protected $description = 'Superviser les workers Docker locaux Aydra, sans agir sur la production.';

    private const array SERVICES = ['provisioning', 'documents', 'account-mail', 'scheduler'];

    public function handle(): int
    {
        $action = $this->argument('action');
        if ($action === 'probe') {
            return $this->probe();
        }
        if (! app()->environment('local') || tenancy()->initialized || PHP_OS_FAMILY !== 'Windows') {
            if (in_array($action, ['auto-start', 'dev-start'], true)) {
                return self::SUCCESS;
            }
            $this->error('Commande réservée à Windows et APP_ENV=local.');

            return self::FAILURE;
        }
        $driver = config('aydra-workers.driver');
        if ($driver !== 'docker') {
            if ($action === 'auto-start' || ($action === 'dev-start' && $driver === 'off')) {
                return self::SUCCESS;
            }
            if ($action === 'dev-start' && $driver === 'windows') {
                return $this->call('aydra:local-queue', ['action' => 'start']);
            }
            if (! in_array($action, ['status', 'stop', 'logs'], true)) {
                $this->error('Activez AYDRA_LOCAL_WORKERS=docker dans le .env local.');

                return self::FAILURE;
            }
        }
        if (! in_array($action, ['auto-start', 'dev-start', 'start', 'check', 'status', 'stop', 'restart', 'logs'], true)) {
            $this->error('Action inconnue.');

            return self::FAILURE;
        }
        $lock = fopen(storage_path('framework/aydra-docker.lock'), 'c');
        if ($lock === false) {
            $this->error('Impossible de créer le verrou de supervision local.');

            return self::FAILURE;
        }
        try {
            if (! flock($lock, LOCK_EX | LOCK_NB)) {
                throw new RuntimeException('Une autre commande Docker Aydra est déjà en cours.');
            }
            // Only the local Docker Desktop engine, never a remote Docker context.
            $context = new Process(['docker', 'context', 'inspect', '--format', '{{.Endpoints.docker.Host}}'], base_path());
            $context->setTimeout(15);
            $context->mustRun();
            if (! in_array(trim($context->getOutput()), ['npipe:////./pipe/docker_engine', 'npipe:////./pipe/dockerDesktopLinuxEngine'], true)
                || getenv('DOCKER_HOST') || getenv('DOCKER_CONTEXT')) {
                throw new RuntimeException('Utilisez le contexte Docker Desktop local sans DOCKER_HOST ni DOCKER_CONTEXT forcé.');
            }
            $this->runProcess(['docker', 'info', '--format', '{{.OSType}}'], [], false, 30, 'linux');
            $this->runProcess(['docker', 'compose', 'version'], [], false, 30);
            $dockerfile = base_path('deployment/docker/Dockerfile');
            $image = 'aydra-workers-local:'.substr(hash_file('sha256', $dockerfile), 0, 16);
            $project = 'aydra-'.substr(hash('sha256', strtolower(str_replace('\\', '/', base_path()))), 0, 12);
            $lockHash = hash('sha256', hash_file('sha256', base_path('composer.json')).hash_file('sha256', base_path('composer.lock')).$image.hash_file('sha256', base_path('deployment/docker/install-dependencies.sh')));
            $mailHost = (string) config('mail.mailers.smtp.host');
            if (in_array($mailHost, ['localhost', '127.0.0.1', '::1'], true)) {
                $mailHost = 'host.docker.internal';
            }
            $env = [
                'AYDRA_WORKER_IMAGE' => $image,
                'AYDRA_VENDOR_VOLUME' => $project.'-vendor-'.substr($lockHash, 0, 16),
                'AYDRA_CONTAINER_MAIL_HOST' => $mailHost,
                'AYDRA_CONFIG_SIGNATURE' => $this->signatureForConfig(),
                'AYDRA_WORKER_REVISION' => $this->revision($lockHash),
            ];
            $compose = ['docker', 'compose', '--project-name', $project, '--env-file', base_path('deployment/docker/compose.env'), '-f', base_path('deployment/docker/compose.yml')];
            if (in_array($action, ['status', 'stop', 'logs'], true)) {
                $args = match ($action) {
                    'status' => ['ps', '-a'],
                    'stop' => ['stop', ...self::SERVICES],
                    'logs' => ['logs', '--tail=100', ...self::SERVICES],
                };
                $this->runProcess([...$compose, ...$args], $env);

                return self::SUCCESS;
            }
            $this->assertLocalConfig(false);
            $this->assertWindowsWorkersStopped();
            $inspect = new Process(['docker', 'image', 'inspect', $image], base_path());
            $inspect->setTimeout(30);
            $inspect->run();
            if (! $inspect->isSuccessful()) {
                $this->info('Construction locale du runtime PHP Aydra.');
                $this->runProcess(['docker', 'build', '--tag', $image, '--file', $dockerfile, base_path('deployment/docker')], [], true, 1800);
            }
            $this->runProcess([...$compose, 'config', '--quiet'], $env, false, 30);
            // Do not replace dependencies used by a running worker: volume is keyed by lock/image.
            $this->runProcess([...$compose, 'run', '--rm', '--no-deps', 'dependencies'], $env, true, 900);
            $this->runProcess([...$compose, 'run', '--rm', '--no-deps', '--entrypoint', 'php', 'provisioning', 'artisan', 'aydra:docker-workers', 'probe', '--no-interaction'], $env, true, 120);
            if ($action === 'check') {
                $this->info('Précontrôles réussis. Aucun worker permanent démarré par cette commande.');

                return self::SUCCESS;
            }
            if ($action === 'restart') {
                $this->runProcess([...$compose, 'stop', ...self::SERVICES], $env, true, 420);
            }
            $this->runProcess([...$compose, 'up', '-d', '--no-deps', '--wait', '--wait-timeout', '60', ...self::SERVICES], $env, true, 600);
            $this->runProcess([...$compose, 'ps'], $env);
            $this->info('Conteneurs démarrés. Leur état ne prouve pas la réussite des jobs.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            // Never dump Process exceptions, environment, credentials or configuration.
            $this->error($exception instanceof RuntimeException && ! $exception instanceof \Symfony\Component\Process\Exception\ExceptionInterface
                ? $exception->getMessage() : 'Démarrage Docker impossible. Vérifiez Docker Desktop, la configuration locale et les journaux.');

            return self::FAILURE;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function assertLocalConfig(bool $container): void
    {
        $host = config('database.connections.mysql.host');
        if (! in_array($host, $container ? ['host.docker.internal'] : ['127.0.0.1', 'localhost'], true)
            || config('database.default') !== 'mysql'
            || config('tenancy.database.central_connection') !== 'mysql'
            || config('database.connections.mysql.url') || config('database.connections.mysql.unix_socket')
            || config('database.connections.mysql.options')
            || config('queue.default') !== 'database'
            || config('queue.connections.database.connection') !== 'mysql'
            || config('queue.connections.database.queue') !== 'default'
            || config('queue.connections.central-provisioning.connection') !== 'mysql'
            || config('queue.account_mail_connection') !== 'database'
            || (int) config('queue.connections.database.retry_after') <= 60
            || (int) config('queue.connections.central-provisioning.retry_after') <= 300
            || config('cache.default') !== 'database'
            || config('filesystems.default') !== 'local'
            || config('mail.mailers.smtp.url')
            || ! config('app.key') || app()->configurationIsCached()) {
            throw new RuntimeException('Configuration locale incompatible : vérifier .env, MySQL local, queues, cache database, stockage local, APP_KEY et config:clear. Aucun accès distant autorisé.');
        }
    }

    private function signatureForConfig(): string
    {
        // Host aliases differ intentionally. Compare effective credentials and other settings without printing them.
        $keys = ['app.env', 'app.url', 'database.default', 'database.connections.mysql.database', 'database.connections.mysql.port',
            'database.connections.mysql.username', 'database.connections.mysql.password', 'database.connections.mysql.charset',
            'database.connections.mysql.collation', 'tenancy.database.central_connection', 'queue.default', 'queue.account_mail_connection',
            'queue.connections.database.connection', 'queue.connections.database.queue', 'queue.connections.database.retry_after',
            'queue.connections.central-provisioning.connection', 'queue.connections.central-provisioning.retry_after', 'cache.default',
            'cache.prefix', 'filesystems.default', 'mail.default', 'mail.mailers.smtp.port', 'mail.mailers.smtp.username',
            'mail.mailers.smtp.password', 'mail.mailers.smtp.scheme', 'mail.mailers.smtp.timeout', 'mail.from.address', 'mail.from.name'];
        $values = array_map(fn (string $key): mixed => config($key), $keys);
        $mailHost = (string) config('mail.mailers.smtp.host');
        $values[] = in_array($mailHost, ['localhost', '127.0.0.1', '::1'], true) ? 'host.docker.internal' : $mailHost;

        return hash_hmac('sha256', json_encode($values, JSON_THROW_ON_ERROR), (string) config('app.key'));
    }

    private function probe(): int
    {
        try {
            if (PHP_OS_FAMILY !== 'Linux' || getenv('AYDRA_DOCKER_WORKER') !== '1' || ! app()->environment('local') || tenancy()->initialized) {
                throw new RuntimeException('Sonde réservée au conteneur local.');
            }
            $this->assertLocalConfig(true);
            if (! hash_equals($this->signatureForConfig(), (string) getenv('AYDRA_CONFIG_SIGNATURE'))) {
                throw new RuntimeException('Configuration effective différente entre Windows et Docker.');
            }
            DB::connection('mysql')->getPdo();
            foreach (['jobs', 'failed_jobs', 'cache', 'cache_locks', 'users', 'tenants'] as $table) {
                if (! Schema::connection('mysql')->hasTable($table)) {
                    throw new RuntimeException('Table centrale manquante : '.$table);
                }
            }
            if (! is_writable(storage_path('app/private')) || ! is_writable(storage_path('logs')) || ! is_writable(base_path('bootstrap/cache'))) {
                throw new RuntimeException('Stockage partagé ou cache non accessible en écriture.');
            }
            $this->info('OK : PHP/Laravel, configuration, MySQL central et stockage. Aucun job consommé.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Sonde échouée : '.$exception::class.'. Vérifiez MySQL, droits Docker, APP_KEY, .env et stockage.');

            return self::FAILURE;
        }
    }

    private function assertWindowsWorkersStopped(): void
    {
        $suffix = substr(hash('sha256', strtolower(base_path())), 0, 12);
        $names = ['Aydra-Provisioning-', 'Aydra-Documents-', 'Aydra-Account-Mail-', 'Aydra-Scheduler-'];
        $list = implode(',', array_map(fn (string $prefix): string => "'".$prefix.$suffix."'", $names));
        $script = '$ErrorActionPreference="Stop"; foreach ($name in @('.$list.')) { $task=Get-ScheduledTask -TaskName $name -ErrorAction SilentlyContinue; if ($task -and ($task.Settings.Enabled -or $task.State -eq "Running")) { exit 9 } }; exit 0';
        $process = new Process([getenv('SystemRoot').'/System32/WindowsPowerShell/v1.0/powershell.exe', '-NoProfile', '-NonInteractive', '-Command', $script], base_path());
        $process->setTimeout(30);
        $process->run();
        if (! $process->isSuccessful()) {
            throw new RuntimeException('Arrêtez les anciennes tâches Windows avec php artisan aydra:local-queue stop, puis arrêtez tout worker manuel.');
        }
    }

    private function revision(string $lockHash): string
    {
        $hash = hash_init('sha256');
        hash_update($hash, $lockHash);
        foreach (['app', 'config', 'routes', 'database', 'resources', 'bootstrap', 'deployment/docker'] as $dir) {
            $paths = [];
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path($dir), \FilesystemIterator::SKIP_DOTS)) as $file) {
                if ($file->isFile() && ! $file->isLink() && ! str_contains(str_replace('\\', '/', $file->getPathname()), '/bootstrap/cache/')) {
                    $paths[] = $file->getPathname();
                }
            }
            sort($paths, SORT_STRING);
            foreach ($paths as $path) {
                hash_update($hash, $path);
                hash_update_file($hash, $path);
            }
        }
        // HMAC rather than exposing the environment or its plain hash as a label.
        hash_update($hash, hash_hmac('sha256', (string) file_get_contents(base_path('.env')), (string) config('app.key')));

        return hash_final($hash);
    }

    private function runProcess(array $command, array $env = [], bool $show = true, int $timeout = 420, ?string $expected = null): void
    {
        $process = new Process($command, base_path(), $env);
        $process->setTimeout($timeout);
        $process->run($show ? function (string $type, string $buffer): void { $this->output->write($buffer); } : null);
        if (! $process->isSuccessful() || ($expected !== null && trim($process->getOutput()) !== $expected)) {
            throw new RuntimeException('Échec de '.implode(' ', array_slice($command, 0, 2)).'. Vérifiez Docker Desktop en mode Linux et les messages précédents.');
        }
    }
}
