import type { UseMutationResult } from '@tanstack/react-query';
import { ShieldOff } from 'lucide-react';
import { useState } from 'react';
import ConfirmDialog from '@/components/shared/ConfirmDialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { apiErrorMessage } from '@/lib/format';
import type { TenantUser } from '@/types';

export function MfaBadge({ user }: { user: TenantUser }) {
    return user.mfa_enabled ? <Badge variant="success">Activa</Badge> : <Badge variant="neutral">Sin configurar</Badge>;
}

/**
 * Restablecer el MFA de un usuario que perdió dispositivo, códigos y correo
 * de respaldo. El backend pide re-autenticación (ReauthModal).
 */
export function ResetMfaAction({
    user,
    reset,
}: {
    user: TenantUser;
    reset: UseMutationResult<void, Error, number>;
}) {
    const [open, setOpen] = useState(false);

    if (!user.mfa_enabled) return null;

    return (
        <>
            <Tooltip>
                <TooltipTrigger asChild>
                    <Button variant="ghosterror" size="icon-sm" aria-label="Restablecer verificación en dos pasos" onClick={() => setOpen(true)}>
                        <ShieldOff />
                    </Button>
                </TooltipTrigger>
                <TooltipContent>Restablecer verificación en dos pasos</TooltipContent>
            </Tooltip>
            <ConfirmDialog
                open={open}
                title="Restablecer verificación en dos pasos"
                description={`${user.name} perderá su app de autenticación, sus códigos y su correo de respaldo, y tendrá que configurarlos de nuevo al entrar. Hazlo solo si confirmaste su identidad por otro medio.`}
                confirmLabel="Restablecer"
                destructive
                loading={reset.isPending}
                onConfirm={() => reset.mutate(user.id, { onSuccess: () => setOpen(false) })}
                onCancel={() => setOpen(false)}
            >
                {reset.isError && <p className="text-sm text-error">{apiErrorMessage(reset.error, ['user'], 'No se pudo restablecer.')}</p>}
            </ConfirmDialog>
        </>
    );
}
