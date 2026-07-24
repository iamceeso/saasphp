<?php

declare(strict_types=1);

use Doctum\Doctum;
use Symfony\Component\Finder\Finder;

$iterator = Finder::create()
    ->files()
    ->name('*.php')
    ->in(__DIR__ . '/app');

return new Doctum($iterator, [
    'title' => 'SaaS PHP API',
    'build_dir' => __DIR__ . '/build/api',
    'cache_dir' => __DIR__ . '/.doctum/cache',
]);
