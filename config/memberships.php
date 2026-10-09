<?php

declare(strict_types=1);

return [
    /*
    | Si es true, un disparo que supere la cuota mensual del canal se rechaza.
    | Si es false, la cuota solo se muestra como consumo.
    */
    'enforce_quotas' => (bool) env('MEMBERSHIPS_ENFORCE_QUOTAS', false),

    // Días antes del vencimiento en que se avisa a la plataforma.
    'expiry_warning_days' => 7,
];
