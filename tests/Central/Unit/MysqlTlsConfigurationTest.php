<?php

use Symfony\Component\Process\Process;

test('MySQL TLS configuration preserves local access and rejects a required missing CA', function (string $required, string $ca, bool $allowed): void {
    $code = <<<'PHP'
    require 'vendor/autoload.php';
    try {
        $config = require 'config/database.php';
        echo json_encode(['valid' => true, 'options' => $config['connections']['mysql']['options']]);
    } catch (InvalidArgumentException $exception) {
        echo json_encode(['valid' => false]);
    }
    PHP;
    $process = new Process([PHP_BINARY, '-r', $code], dirname(__DIR__, 3), [
        'MYSQL_SSL_REQUIRED' => $required, 'MYSQL_ATTR_SSL_CA' => $ca,
    ]);
    $process->mustRun();
    $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

    expect($result['valid'])->toBe($allowed);
    if ($allowed) {
        expect($result['options'])->toBe([]);
    }
})->with([
    ['false', '', true], ['true', '', false],
    ['true', 'Z:/missing-aydra-ca.pem', false],
]);

test('a readable CA enables certificate verification on MySQL and its MariaDB configuration', function (): void {
    $ca = tempnam(sys_get_temp_dir(), 'aydra-ca-');
    file_put_contents($ca, 'certificate placeholder; this test checks configuration without connecting');
    try {
        $code = <<<'PHP'
        require 'vendor/autoload.php';
        $config = require 'config/database.php';
        echo json_encode([
            'ca' => $config['connections']['mysql']['options'][Pdo\Mysql::ATTR_SSL_CA],
            'verify' => $config['connections']['mysql']['options'][Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT],
            'same' => $config['connections']['mysql']['options'] === $config['connections']['mariadb']['options'],
        ]);
        PHP;
        $process = new Process([PHP_BINARY, '-r', $code], dirname(__DIR__, 3), [
            'MYSQL_SSL_REQUIRED' => 'true', 'MYSQL_ATTR_SSL_CA' => $ca,
        ]);
        $process->mustRun();
        $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        expect($result)->toBe(['ca' => $ca, 'verify' => true, 'same' => true]);
    } finally {
        unlink($ca);
    }
});
