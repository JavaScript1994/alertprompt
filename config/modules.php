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
| Los módulos con los que nace un cliente los define su plan (tabla plans,
| Administración > Planes). Después se activan o desactivan uno a uno.
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

];
