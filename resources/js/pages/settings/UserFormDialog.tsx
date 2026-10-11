import { zodResolver } from '@hookform/resolvers/zod';
import { XCircle } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { z } from 'zod';
import { Alert, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useAssignableRoles, useInviteUser } from '@/hooks/useUsers';
import { applyServerErrors } from '@/lib/forms';
import type { TenantUser } from '@/types';
import UserDetailDialog from './UserDetailDialog';

const schema = z.object({
    first_name: z.string().trim().min(1, 'Ingresa los nombres').max(80),
    last_name: z.string().trim().min(1, 'Ingresa los apellidos').max(80),
    email: z.string().trim().min(1, 'Ingresa el correo').email('Correo inválido'),
    role_id: z.string().min(1, 'Elige un rol'),
});

type FormValues = z.infer<typeof schema>;

const FIELDS = ['first_name', 'last_name', 'email', 'role_id'] as const;

/** Invitar: lo mínimo para el acceso. El resto de la ficha lo completa la persona en Mi perfil. */
function InviteUserDialog({ open, onClose }: { open: boolean; onClose: () => void }) {
    const { data: roles } = useAssignableRoles();
    const invite = useInviteUser();
    const [generalError, setGeneralError] = useState<string | null>(null);

    const {
        register,
        control,
        handleSubmit,
        reset,
        watch,
        setError,
        formState: { errors },
    } = useForm<FormValues>({ resolver: zodResolver(schema) });

    useEffect(() => {
        if (open) reset({ first_name: '', last_name: '', email: '', role_id: '' });
    }, [open, reset]);

    const close = () => {
        setGeneralError(null);
        onClose();
    };

    const onSubmit = handleSubmit((values) =>
        invite.mutate(
            { ...values, role_id: Number(values.role_id) },
            { onSuccess: close, onError: (error) => setGeneralError(applyServerErrors(error, setError, FIELDS)) },
        ),
    );

    const selectedRole = roles?.find((role) => String(role.id) === watch('role_id'));

    return (
        <Dialog open={open} onOpenChange={(isOpen) => !isOpen && close()}>
            <DialogContent className="max-w-lg">
                <DialogHeader>
                    <DialogTitle>Invitar usuario</DialogTitle>
                    <DialogDescription>
                        Recibirá un correo para crear su contraseña (el enlace dura 7 días). El resto de sus datos los completa
                        en Mi perfil.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={onSubmit} className="space-y-4" noValidate>
                    {generalError && (
                        <Alert variant="error">
                            <XCircle />
                            <AlertTitle>{generalError}</AlertTitle>
                        </Alert>
                    )}

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <Field label="Nombres" htmlFor="invite-first-name" error={errors.first_name?.message}>
                            <Input id="invite-first-name" aria-invalid={Boolean(errors.first_name)} {...register('first_name')} />
                        </Field>
                        <Field label="Apellidos" htmlFor="invite-last-name" error={errors.last_name?.message}>
                            <Input id="invite-last-name" aria-invalid={Boolean(errors.last_name)} {...register('last_name')} />
                        </Field>
                    </div>

                    <Field label="Correo" htmlFor="invite-email" error={errors.email?.message} hint="Será su usuario para iniciar sesión.">
                        <Input id="invite-email" type="email" aria-invalid={Boolean(errors.email)} {...register('email')} />
                    </Field>

                    <Field label="Rol" htmlFor="invite-role" error={errors.role_id?.message} hint={selectedRole?.description ?? undefined}>
                        <Controller
                            control={control}
                            name="role_id"
                            render={({ field }) => (
                                <Select value={field.value} onValueChange={(value) => value && field.onChange(value)}>
                                    <SelectTrigger id="invite-role" className="w-full" aria-invalid={Boolean(errors.role_id)}>
                                        <SelectValue placeholder="Elige un rol" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {roles?.map((role) => (
                                            <SelectItem key={role.id} value={String(role.id)}>
                                                {role.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            )}
                        />
                    </Field>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={close}>
                            Cancelar
                        </Button>
                        <Button type="submit" loading={invite.isPending}>
                            Enviar invitación
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

/** Invitar (sin `user`) o ver y editar la ficha completa de un usuario. */
export default function UserFormDialog({ open, user, onClose }: { open: boolean; user?: TenantUser; onClose: () => void }) {
    if (user) return <UserDetailDialog key={user.id} open={open} userId={user.id} fallback={user} onClose={onClose} />;

    return <InviteUserDialog open={open} onClose={onClose} />;
}
