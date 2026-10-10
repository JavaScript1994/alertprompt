<?php

declare(strict_types=1);

namespace App\Enums;

/** Eventos de autenticación que quedan en `auth_events`. */
enum AuthEventType: string
{
    /** Contraseña correcta (falta o no el segundo factor). */
    case Login = 'login';
    case MfaChallenge = 'mfa_challenge';
    case MfaSuccess = 'mfa_success';
    case MfaFailed = 'mfa_failed';
    case RecoveryCodeUsed = 'recovery_code_used';
    case RecoveryCodesRegenerated = 'recovery_codes_regenerated';
    case EmailBackupUsed = 'email_backup_used';
    case EmailBackupRequested = 'email_backup_requested';
    case EmailBackupChanged = 'email_backup_changed';
    case MfaEnrolled = 'mfa_enrolled';
    case MfaDisabled = 'mfa_disabled';
    /** Un administrador borró el MFA de otro usuario (perdió todo). */
    case MfaReset = 'mfa_reset';
    case ReauthRequired = 'reauth_required';
    case ReauthSuccess = 'reauth_success';
    case ReauthFailed = 'reauth_failed';
}
