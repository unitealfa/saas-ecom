<?php

use App\Models\Central\Billing\BillingRule;
use App\Models\Central\Roles\Role;
use App\Models\User;
use Database\Seeders\Central\AuthorizationSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

test('the isolated browser exercises rule creation validation activation and versioning', function (): void {
    $this->seed(AuthorizationSeeder::class);
    $actor = User::factory()->create(['email' => 'billing-browser@example.test']);
    $actor->assignRole(Role::findByName('root', 'central'));
    $database = DB::connection()->getDatabaseName();
    expect($database)->toStartWith('aydra_test_');
    $runtime = storage_path('framework/testing/module18-browser-'.Str::uuid());
    foreach (['framework/views', 'framework/cache/data', 'framework/sessions', 'logs', 'app/private'] as $directory) {
        File::ensureDirectoryExists($runtime.'/'.$directory);
    }
    $socket = stream_socket_server('tcp://127.0.0.1:0', $error, $message);
    expect($socket)->not->toBeFalse();
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $port = (int) substr(strrchr($address, ':'), 1);
    $base = 'http://127.0.0.1:'.$port;
    $connection = config('database.connections.mysql');
    $environment = [
        'APP_ENV' => 'testing', 'APP_URL' => $base, 'SAAS_BASE_DOMAIN' => '127.0.0.1',
        'DB_CONNECTION' => 'mysql', 'DB_URL' => false, 'DB_DATABASE' => $database,
        'DB_HOST' => $connection['host'], 'DB_PORT' => (string) $connection['port'],
        'DB_USERNAME' => $connection['username'], 'DB_PASSWORD' => $connection['password'],
        'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'file', 'SESSION_SECURE_COOKIE' => 'false',
        'SESSION_DOMAIN' => '(null)', 'SESSION_COOKIE' => 'module18_'.Str::random(12),
        'LARAVEL_STORAGE_PATH' => $runtime, 'MAIL_MAILER' => 'log',
        'APP_CONFIG_CACHE' => $runtime.'/config.php', 'APP_ROUTES_CACHE' => $runtime.'/routes.php',
    ];
    DB::commit();
    $server = new Process([PHP_BINARY, 'artisan', 'serve', '--host=127.0.0.1', '--port='.$port, '--no-reload', '--no-interaction'], base_path(), $environment);
    $server->setTimeout(null);
    try {
        $server->start();
        fwrite(STDOUT, "\nBROWSER_READY ".json_encode(['url' => $base, 'completion' => $runtime.'/complete.json'])."\n");
        $deadline = microtime(true) + 900;
        while (! is_file($runtime.'/complete.json') && microtime(true) < $deadline && $server->isRunning()) {
            usleep(200000);
        }
        expect(is_file($runtime.'/complete.json'))->toBeTrue($server->getErrorOutput());
        expect(BillingRule::where('code', 'browser.rule')->count())->toBe(2);
        expect(BillingRule::where('code', 'browser.rule')->where('version', 1)->sole()->parameters['payment_due_days'])->toBe(20);
    } finally {
        $server->stop();
        DB::beginTransaction();
        File::deleteDirectory($runtime);
    }
})->skip(fn (): bool => getenv('AYDRA_MODULE18_BROWSER') !== '1', 'Interactive isolated browser session only.');
