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
    'theme' => 'saasphp',
    'template_dirs' => [__DIR__ . '/docs/doctum-theme'],
    'build_dir' => __DIR__ . '/build/api',
    'cache_dir' => __DIR__ . '/.doctum/cache',
    'footer_link' => [
        'href' => 'https://saasphp.com',
        'rel' => 'noreferrer noopener',
        'target' => '_blank',
        'link_text' => 'Return to SaaS PHP homepage',
    ],
]);
