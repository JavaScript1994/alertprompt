/** Estado del segundo factor del usuario autenticado (MfaStatusResource). */
export interface MfaStatus {
    enabled: boolean;
    confirmed_at: string | null;
    /** Debe enrolar ya: el backend bloquea todo lo demás con 403. */
    required_now: boolean;
    /** Plazo para enrolar de quien no administra usuarios. */
    grace_ends_at: string | null;
    session_verified: boolean;
    /** Entró con un respaldo: puede configurar un dispositivo nuevo sin re-autenticar. */
    can_reenroll: boolean;
    /** Correo de respaldo verificado, enmascarado (a*****@dominio.com). */
    email_backup: string | null;
    recovery_codes_remaining: number;
    should_regenerate_codes: boolean;
}

export interface MfaSetup {
    secret: string;
    otpauth_url: string;
    qr_svg: string;
}

/** Respuesta de POST /api/login cuando el usuario tiene MFA. */
export interface LoginChallenge {
    mfa_required: true;
    challenge_token: string;
    expires_in: number;
    email_backup_available: boolean;
}

export type SensitiveAction =
    | 'export_contacts'
    | 'change_provider_config'
    | 'change_user_role'
    | 'disable_mfa'
    | 'regenerate_recovery_codes'
    | 'reset_user_mfa'
    | 'change_authenticator'
    | 'change_email_backup';

export type MfaErrorCode =
    | 'mfa_enrollment_required'
    | 'mfa_challenge_required'
    | 'reauth_required'
    | 'challenge_invalid'
    | 'email_backup_unavailable'
    | 'too_many_attempts';

/** Cuerpo de los errores del flujo de MFA (MfaException). */
export interface MfaErrorBody {
    message: string;
    code: MfaErrorCode;
    action?: SensitiveAction;
    action_label?: string;
    retry_after?: number;
}
