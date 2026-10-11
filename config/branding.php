<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Marca por empresa en el login (subdominios)
|--------------------------------------------------------------------------
|
| Cada cliente tiene un identificador (`tenants.slug`) y su login vive en
| {slug}.{base_domain}. Ahí solo entran usuarios de esa empresa. Con el
| módulo `branding` activo, ese login muestra su logo, color y título.
| El dominio base (sin subdominio) es el login general de AlertPrompt.
|
| Producción: DNS comodín *.{base_domain}, certificado comodín y nginx
| aceptando todos los subdominios. En local, *.localhost ya resuelve solo.
|
*/

$appUrl = (string) env('APP_URL', 'http://localhost');

return [
    'base_domain' => env('APP_BASE_DOMAIN', parse_url($appUrl, PHP_URL_HOST) ?: 'localhost'),

    'module' => 'branding',

    // Subdominios que nunca pueden ser de un cliente.
    'reserved_slugs' => [
        'www', 'api', 'app', 'admin', 'administracion', 'panel', 'login', 'auth', 'mail', 'email', 'smtp',
        'ftp', 'cdn', 'static', 'assets', 'storage', 'files', 'help', 'ayuda', 'soporte', 'support',
        'status', 'blog', 'docs', 'dev', 'test', 'staging', 'demo', 'alertprompt', 'plataforma', 'webhooks',
    ],

    'logo' => [
        'max_kb' => 1024,
        'min_px' => 64,
        'max_px' => 2000,
    ],

    // Color de marca por defecto (var --tenant-accent de app.css).
    'default_color' => '#147b70',
];
