<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Acciones que exigen re-autenticación reciente (contraseña + TOTP).
 *
 * Revocar un consentimiento NO está aquí a propósito: por la Ley 32323 la
 * baja tiene efecto inmediato y no puede quedar detrás de un paso extra.
 */
enum SensitiveAction: string
{
    /** Exportar reportes con datos de contactos. */
    case ExportContacts = 'export_contacts';
    case ChangeProviderConfig = 'change_provider_config';
    case ChangeUserRole = 'change_user_role';
    case DisableMfa = 'disable_mfa';
    case RegenerateRecoveryCodes = 'regenerate_recovery_codes';
    case ResetUserMfa = 'reset_user_mfa';
    /** Cambiar de dispositivo de autenticación teniendo el anterior. */
    case ChangeAuthenticator = 'change_authenticator';
    /** Reemplazar un correo de respaldo ya verificado. */
    case ChangeEmailBackup = 'change_email_backup';

    public function label(): string
    {
        return match ($this) {
            self::ExportContacts => 'Exportar datos de contactos',
            self::ChangeProviderConfig => 'Cambiar la configuración de un proveedor',
            self::ChangeUserRole => 'Cambiar usuarios o roles',
            self::DisableMfa => 'Desactivar la verificación en dos pasos',
            self::RegenerateRecoveryCodes => 'Regenerar los códigos de recuperación',
            self::ResetUserMfa => 'Restablecer la verificación en dos pasos de un usuario',
            self::ChangeAuthenticator => 'Cambiar de app de autenticación',
            self::ChangeEmailBackup => 'Cambiar el correo de respaldo',
        };
    }
}
