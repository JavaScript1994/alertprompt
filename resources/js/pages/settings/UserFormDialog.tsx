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
import { useAssignableRoles, useInviteUser, useUpdateUser } from '@/hooks/useUsers';
import { applyServerErrors } from '@/lib/forms';
import type { TenantUser } from '@/types';

const schema = z.object({
    name: z.string().trim().min(1, 'Ingresa el nombre').max(120),
    email: z.string().trim(),
    role_id: z.string().min(1, 'Elige un rol'),
});

type FormValues = z.infer<typeof schema>;

const FIELDS = ['name', 'email', 'role_id'] as const;

/** Invitar (con correo) o editar nombre y rol de un usuario. */
export default function UserFormDialog({ open, user, onClose }: { open: boolean; user?: TenantUser; onClose: () => void }) {
    const isEditing = user !== undefined;
    const { data: roles } = useAssignableRoles();
    const invite = useInviteUser();
    const update = useUpdateUser();
    const mutation = isEditing ? update : invite;
    const [generalError, setGeneralError] = useState<string | null>(null);

    const {
        register,
        control,
        handleSubmit,
        reset,
        watch,
        setError,
        formState: { errors },
    } = useForm<FormValues>({
        resolver: zodResolver(
            isEditing ? schema : schema.extend({ email: z.string().trim().min(1, 'Ingresa el correo').email('Correo inválido') }),
        ),
    });

    useEffect(() => {
        if (!open) return;
        reset({ name: user?.name ?? '', email: user?.email ?? '', role_id: user?.roles[0] ? String(user.roles[0].id) : '' });
    }, [open, user, reset]);

    const close = () => {
        setGeneralError(null);
        onClose();
    };

    const onSubmit = handleSubmit((values) => {
        setGeneralError(null);
        const onError = (error: unknown) => setGeneralError(applyServerErrors(error, setError, FIELDS));
        const input = { name: values.name, role_id: Number(values.role_id) };

        if (isEditing) {
            update.mutate({ ...input, id: user.id }, { onSuccess: close, onError });
        } else {
            invite.mutate({ ...input, email: values.email }, { onSuccess: close, onError });
        }
    });

    const selectedRole = roles?.find((role) => String(role.id) === watch('role_id'));

    return (
        <Dialog open={open} onOpenChange={(isOpen) => !isOpen && close()}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>{isEditing ? 'Editar usuario' : 'Invitar usuario'}</DialogTitle>
                    <DialogDescription>
                        {isEditing ? user.email : 'Recibirá un correo para crear su contraseña. El enlace dura 7 días.'}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={onSubmit} className="space-y-4" noValidate>
                    {generalError && (
                        <Alert variant="error">
                            <XCircle />
                            <AlertTitle>{generalError}</AlertTitle>
                        </Alert>
                    )}

                    <Field label="Nombre" htmlFor="user-name" error={errors.name?.message}>
                        <Input id="user-name" aria-invalid={Boolean(errors.name)} {...register('name')} />
                    </Field>

                    {!isEditing && (
                        <Field label="Correo" htmlFor="user-email" error={errors.email?.message}>
                            <Input id="user-email" type="email" aria-invalid={Boolean(errors.email)} {...register('email')} />
                        </Field>
                    )}

                    <Field label="Rol" htmlFor="user-role" error={errors.role_id?.message} hint={selectedRole?.description ?? undefined}>
                        <Controller
                            control={control}
                            name="role_id"
                            render={({ field }) => (
                                <Select value={field.value} onValueChange={(value) => value && field.onChange(value)}>
                                    <SelectTrigger id="user-role" className="w-full" aria-invalid={Boolean(errors.role_id)}>
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
                        <Button type="submit" loading={mutation.isPending}>
                            {isEditing ? 'Guardar cambios' : 'Enviar invitación'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
