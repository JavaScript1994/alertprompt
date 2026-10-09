<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Árbol de permisos
|--------------------------------------------------------------------------
|
| Sección → módulo → acción. El nombre del permiso es "{módulo}.{acción}"
| (p. ej. "campaigns.dispatch"). Los permisos viven en código porque cada uno
| corresponde a un chequeo real en rutas o controladores: un permiso creado
| desde la UI no protegería nada. Los ROLES sí son dinámicos (los crea el
| dueño de la plataforma) y combinan estos permisos.
|
| Después de editar este archivo: `php artisan permissions:sync`.
|
| `scope` indica a qué panel pertenece la sección: un rol de cliente solo
| puede contener permisos `client`; un rol de plataforma puede contener ambos,
| porque AlertPrompt también usa el panel de cliente para sus propias campañas.
|
*/

return [
    'sections' => [
        'admin' => [
            'label' => 'Administración de la plataforma',
            'scope' => 'platform',
            'modules' => [
                'admin.dashboard' => ['label' => 'Dashboard global', 'actions' => [
                    'view' => 'Ver',
                ]],
                'admin.clients' => ['label' => 'Clientes', 'actions' => [
                    'view' => 'Ver',
                    'create' => 'Crear',
                    'update' => 'Editar',
                    'suspend' => 'Suspender y reactivar',
                ]],
                'admin.supervision' => ['label' => 'Supervisión de clientes', 'actions' => [
                    'view' => 'Ver contactos, plantillas y campañas de clientes (solo lectura)',
                ]],
                'admin.impersonate' => ['label' => 'Soporte', 'actions' => [
                    'use' => 'Entrar al panel de un cliente',
                ]],
                'admin.roles' => ['label' => 'Roles y permisos', 'actions' => [
                    'view' => 'Ver',
                    'manage' => 'Crear, editar y eliminar',
                ]],
                'admin.memberships' => ['label' => 'Membresías', 'actions' => [
                    'view' => 'Ver',
                    'manage' => 'Crear, renovar y cancelar',
                ]],
                'admin.modules' => ['label' => 'Módulos', 'actions' => [
                    'view' => 'Ver',
                    'manage' => 'Activar y desactivar por cliente',
                ]],
                'admin.alerts' => ['label' => 'Alertas', 'actions' => [
                    'view' => 'Ver',
                    'manage' => 'Marcar como resueltas',
                ]],
                'admin.bulk_imports' => ['label' => 'Cargas masivas', 'actions' => [
                    'view' => 'Ver',
                    'create' => 'Cargar a nombre de un cliente',
                ]],
                'admin.reports' => ['label' => 'Reportes globales', 'actions' => [
                    'view' => 'Ver',
                    'export' => 'Exportar CSV',
                ]],
            ],
        ],

        'messaging' => [
            'label' => 'Mensajería',
            'scope' => 'client',
            'modules' => [
                'dashboard' => ['label' => 'Dashboard', 'actions' => [
                    'view' => 'Ver',
                ]],
                'contacts' => ['label' => 'Contactos', 'actions' => [
                    'view' => 'Ver',
                    'create' => 'Crear',
                    'update' => 'Editar',
                    'delete' => 'Eliminar',
                    'import' => 'Importar CSV',
                    'consents' => 'Registrar y revocar consentimientos',
                ]],
                'templates' => ['label' => 'Plantillas', 'actions' => [
                    'view' => 'Ver',
                    'create' => 'Crear',
                    'update' => 'Editar',
                    'delete' => 'Eliminar',
                ]],
                'campaigns' => ['label' => 'Campañas', 'actions' => [
                    'view' => 'Ver',
                    'create' => 'Crear',
                    'update' => 'Editar',
                    'delete' => 'Eliminar',
                    'dispatch' => 'Disparar envío',
                ]],
            ],
        ],

        'reports' => [
            'label' => 'Reportes',
            'scope' => 'client',
            'modules' => [
                'reports' => ['label' => 'Reportes', 'actions' => [
                    'view' => 'Ver',
                    'export' => 'Exportar CSV',
                ]],
            ],
        ],

        'billing' => [
            'label' => 'Facturación',
            'scope' => 'client',
            'modules' => [
                'billing' => ['label' => 'Pagos y comprobantes', 'actions' => [
                    'view' => 'Ver',
                ]],
                'payment_methods' => ['label' => 'Métodos de pago', 'actions' => [
                    'view' => 'Ver',
                    'manage' => 'Agregar y quitar',
                ]],
            ],
        ],

        'settings' => [
            'label' => 'Configuración',
            'scope' => 'client',
            'modules' => [
                'settings' => ['label' => 'Panel', 'actions' => [
                    'view' => 'Ver',
                    'manage' => 'Editar',
                ]],
                'users' => ['label' => 'Usuarios', 'actions' => [
                    'view' => 'Ver',
                    'manage' => 'Invitar, editar y desactivar',
                ]],
                'membership' => ['label' => 'Membresía', 'actions' => [
                    'view' => 'Ver contrato',
                ]],
                'whatsapp_account' => ['label' => 'Cuenta de WhatsApp', 'actions' => [
                    'view' => 'Ver',
                    'manage' => 'Conectar y cambiar número',
                ]],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Roles de sistema
    |--------------------------------------------------------------------------
    |
    | Se crean con `permissions:sync` si no existen. Después el dueño puede
    | editar sus permisos desde la UI; el sync NO los pisa, salvo
    | `platform-owner`, que siempre recibe todos (así nadie deja la plataforma
    | sin administrador). `permissions` acepta '*' (todo), 'scope:client'
    | (todo lo del panel de cliente) o una lista de nombres.
    |
    */

    'system_roles' => [
        'platform-owner' => [
            'label' => 'Dueño de la plataforma',
            'description' => 'Acceso total a la administración y al panel propio de AlertPrompt.',
            'scope' => 'platform',
            'permissions' => '*',
        ],
        'client-admin' => [
            'label' => 'Administrador',
            'description' => 'Acceso total al panel del cliente, incluidos usuarios y facturación.',
            'scope' => 'client',
            'permissions' => 'scope:client',
        ],
        'client-user' => [
            'label' => 'Usuario',
            'description' => 'Opera contactos, plantillas y campañas. Sin facturación ni usuarios.',
            'scope' => 'client',
            'permissions' => [
                'dashboard.view',
                'contacts.view', 'contacts.create', 'contacts.update', 'contacts.import', 'contacts.consents',
                'templates.view', 'templates.create', 'templates.update',
                'campaigns.view', 'campaigns.create', 'campaigns.update', 'campaigns.dispatch',
                'reports.view', 'reports.export',
            ],
        ],
        'client-viewer' => [
            'label' => 'Solo lectura',
            'description' => 'Ve contactos, plantillas, campañas y reportes. No modifica nada.',
            'scope' => 'client',
            'permissions' => [
                'dashboard.view', 'contacts.view', 'templates.view', 'campaigns.view', 'reports.view',
            ],
        ],
    ],
];
