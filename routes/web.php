<?php

use Illuminate\Support\Facades\Route;

$canonicalDomain = parse_url(config('app.url'), PHP_URL_HOST);

foreach (config('tenancy.central_domains') as $index => $domain) {
    $routes = Route::domain($domain);

    if ($domain !== $canonicalDomain) {
        $routes->name('central.alias.'.$index.'.');
    }

    $routes->group(__DIR__.'/central.php');
}
