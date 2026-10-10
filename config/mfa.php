<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Doble factor (MFA) de los usuarios del panel
|--------------------------------------------------------------------------
|
| TOTP (app de autenticación) es el ÚNICO segundo factor del login rutinario.
| El correo de respaldo y los códigos de recuperación solo se usan en el flujo
| "perdí mi dispositivo". Aplica a la tabla `users` (personas que entran al
| panel), nunca a los contactos. Ver CLAUDE.md §2.4.
|
*/

return [
    // Nombre que muestra la app de autenticación junto al correo.
    'issuer' => env('MFA_ISSUER', env('APP_NAME', 'AlertPrompt')),

    // ±1 periodo de 30 s. No ampliar.
    'totp_window' => 1,

    // Quien tiene este permiso enrola en el primer login. El resto (y los
    // roles personalizados sin él) tiene `grace_days` para hacerlo. El
    // Administrador general tiene todos los permisos, así que no tiene gracia.
    'immediate_permission' => 'users.manage',
    'grace_days' => 14,

    'recovery_codes' => [
        'count' => 8,
        // Con estos o menos restantes, el panel exige regenerarlos.
        'regenerate_threshold' => 2,
    ],

    // Token del segundo paso del login (entre contraseña y código).
    'challenge_ttl' => 300,

    'email_otp' => [
        'ttl' => 600,
        'max_attempts' => 5,
    ],

    // Validez de una re-autenticación para acciones sensibles.
    'reauth_ttl' => 900,

    'rate_limits' => [
        'verify_per_user' => 5,        // por minuto
        'verify_per_ip' => 10,         // por minuto
        'recovery_per_user' => 5,      // por minuto
        'confirm_per_user' => 5,       // por minuto
        'email_challenge_per_user' => 3, // cada 15 minutos
        'email_setup_per_user' => 3,     // cada 15 minutos
    ],
];
