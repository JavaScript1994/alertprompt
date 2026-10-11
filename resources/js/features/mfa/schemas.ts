import { z } from 'zod';

export const totpCodeSchema = z
    .string()
    .trim()
    .regex(/^\d{6}$/, 'Ingresa los 6 dígitos');

export const codeFormSchema = z.object({ code: totpCodeSchema });
export type CodeForm = z.infer<typeof codeFormSchema>;

export const recoveryCodeFormSchema = z.object({
    recovery_code: z
        .string()
        .trim()
        .regex(/^[A-Za-z0-9]{5}-?[A-Za-z0-9]{5}$/, 'El código tiene el formato XXXXX-XXXXX'),
});
export type RecoveryCodeForm = z.infer<typeof recoveryCodeFormSchema>;

export const emailBackupFormSchema = z.object({
    email: z.string().trim().min(1, 'Ingresa un correo').email('Correo inválido'),
});
export type EmailBackupForm = z.infer<typeof emailBackupFormSchema>;

export const reauthFormSchema = z.object({
    password: z.string().min(1, 'Ingresa tu contraseña'),
    code: totpCodeSchema,
});
export type ReauthForm = z.infer<typeof reauthFormSchema>;
