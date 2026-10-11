<?php

declare(strict_types=1);

namespace App\Enums;

/** Comunicados de AlertPrompt a sus clientes (no son alertas automáticas). */
enum AnnouncementType: string
{
    /** Nuevas funciones o cambios. Se muestra una vez al entrar. */
    case Feature = 'feature';
    /** Información general: mantenimientos, cambios de horario... */
    case Notice = 'notice';
    /** Algo no funciona. Banner fijo hasta que se marque resuelto. */
    case Incident = 'incident';
    /** El cliente debe hacer algo. Banner hasta que lo descarte. */
    case ActionRequired = 'action_required';
    /** Servicios o planes que puede contratar. Nunca por correo. */
    case Promotion = 'promotion';

    public function label(): string
    {
        return match ($this) {
            self::Feature => 'Novedad',
            self::Notice => 'Aviso',
            self::Incident => 'Incidente',
            self::ActionRequired => 'Acción requerida',
            self::Promotion => 'Promoción',
        };
    }

    /**
     * Solo lo operativo puede ir también por correo: una promoción por
     * correo sin pedirla va contra el espíritu de la Ley 32323 (§2.1).
     */
    public function canEmail(): bool
    {
        return in_array($this, [self::Incident, self::ActionRequired], true);
    }
}
