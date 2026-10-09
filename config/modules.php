<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Módulos contratables
|--------------------------------------------------------------------------
|
| Cada módulo habilita una capacidad para un cliente. Se aplican en el
| backend (middleware `module:` y validaciones) y el panel oculta lo que no
| está activo. El tenant de la plataforma tiene todos siempre.
|
| `plans`: módulos que un cliente recibe al crearse según su plan. Después el
| dueño de la plataforma puede activarlos o desactivarlos uno a uno.
|
*/

return [
    'catalog' => [
        'whatsapp' => ['label' => 'Canal WhatsApp', 'description' => 'Plantillas y campañas por WhatsApp.'],
        'sms' => ['label' => 'Canal SMS', 'description' => 'Plantillas y campañas por SMS.'],
        'email' => ['label' => 'Canal Email', 'description' => 'Plantillas y campañas por correo.'],
        'csv_import' => ['label' => 'Importación CSV', 'description' => 'Carga de contactos desde archivos CSV.'],
        'scheduling' => ['label' => 'Envíos programados', 'description' => 'Programar campañas para una fecha y hora.'],
        'reports' => ['label' => 'Reportes', 'description' => 'Métricas de entrega y exportación.'],
    ],

    'plans' => [
        'starter' => ['sms', 'email', 'csv_import'],
        'growth' => ['whatsapp', 'sms', 'email', 'csv_import', 'scheduling', 'reports'],
        'scale' => ['whatsapp', 'sms', 'email', 'csv_import', 'scheduling', 'reports'],
        'enterprise' => ['whatsapp', 'sms', 'email', 'csv_import', 'scheduling', 'reports'],
    ],
];
