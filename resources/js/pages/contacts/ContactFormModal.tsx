import { zodResolver } from '@hookform/resolvers/zod';
import { AxiosError } from 'axios';
import { XCircle } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useForm } from 'react-hook-form';
import { z } from 'zod';
import { Alert, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { useCreateContact, useUpdateContact } from '@/hooks/useContacts';
import type { Contact } from '@/types';

const contactSchema = z
    .object({
        name: z.string().min(1, 'Ingresa un nombre').max(255),
        phone: z.string().max(32).optional().or(z.literal('')),
        email: z.string().email('Ingresa un email válido').max(255).optional().or(z.literal('')),
    })
    .refine((values) => values.phone || values.email, {
        message: 'Ingresa al menos un teléfono o un email',
        path: ['phone'],
    });

type ContactFormValues = z.infer<typeof contactSchema>;

const EMPTY_VALUES: ContactFormValues = { name: '', phone: '', email: '' };

export default function ContactFormModal({
    open,
    onClose,
    contact,
}: {
    open: boolean;
    onClose: () => void;
    /** Contacto a editar. Si es null/undefined, el modal registra uno nuevo. */
    contact?: Contact | null;
}) {
    const isEditing = Boolean(contact);
    const createContact = useCreateContact();
    const updateContact = useUpdateContact();
    const mutation = isEditing ? updateContact : createContact;

    const {
        register,
        handleSubmit,
        reset,
        setError,
        formState: { errors },
    } = useForm<ContactFormValues>({
        resolver: zodResolver(contactSchema),
        defaultValues: EMPTY_VALUES,
    });

    useEffect(() => {
        if (!open) return;

        reset(
            contact
                ? { name: contact.name, phone: contact.phone ?? '', email: contact.email ?? '' }
                : EMPTY_VALUES,
        );
        createContact.reset();
        updateContact.reset();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, contact]);

    // Errores de validación (ej. teléfono/email duplicado) llegan como 422 con
    // uno o más campos — se marcan sobre el input correspondiente en vez de un
    // mensaje genérico. Solo queda un Alert general para fallas no asociadas a
    // un campo del formulario (ej. error de red o del servidor).
    const [generalError, setGeneralError] = useState<string | null>(null);

    const onSubmit = handleSubmit((values) => {
        setGeneralError(null);

        const input = {
            name: values.name,
            phone: values.phone || undefined,
            email: values.email || undefined,
        };

        const onError = (error: unknown) => {
            if (!(error instanceof AxiosError) || error.response?.status !== 422) {
                setGeneralError('No se pudo guardar el contacto. Intenta nuevamente.');
                return;
            }

            const fieldErrors = error.response.data?.errors as Record<string, string[]> | undefined;
            if (!fieldErrors) {
                setGeneralError(error.response.data?.message ?? 'No se pudo guardar el contacto.');
                return;
            }

            for (const [field, messages] of Object.entries(fieldErrors)) {
                if (field === 'name' || field === 'phone' || field === 'email') {
                    setError(field, { message: messages[0] });
                }
            }
        };

        if (isEditing && contact) {
            updateContact.mutate({ id: contact.id, ...input }, { onSuccess: onClose, onError });
        } else {
            createContact.mutate(input, { onSuccess: onClose, onError });
        }
    });

    return (
        <Dialog open={open} onOpenChange={(isOpen) => !isOpen && onClose()}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>{isEditing ? 'Editar contacto' : 'Registro manual de contacto'}</DialogTitle>
                    <DialogDescription>Teléfono en formato internacional (ej. +51987654321).</DialogDescription>
                </DialogHeader>

                <form onSubmit={onSubmit} className="space-y-4">
                    {generalError && (
                        <Alert variant="error">
                            <XCircle />
                            <AlertTitle>{generalError}</AlertTitle>
                        </Alert>
                    )}

                    <Field label="Nombre" htmlFor="contact-name" error={errors.name?.message}>
                        <Input
                            id="contact-name"
                            placeholder="Ana Torres"
                            aria-invalid={Boolean(errors.name)}
                            {...register('name')}
                        />
                    </Field>
                    <Field label="Teléfono" htmlFor="contact-phone" error={errors.phone?.message}>
                        <Input
                            id="contact-phone"
                            placeholder="+51987654321"
                            aria-invalid={Boolean(errors.phone)}
                            {...register('phone')}
                        />
                    </Field>
                    <Field label="Email" htmlFor="contact-email" error={errors.email?.message}>
                        <Input
                            id="contact-email"
                            type="email"
                            placeholder="ana.torres@example.com"
                            aria-invalid={Boolean(errors.email)}
                            {...register('email')}
                        />
                    </Field>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose}>
                            Cancelar
                        </Button>
                        <Button type="submit" loading={mutation.isPending}>
                            {mutation.isPending ? 'Guardando…' : isEditing ? 'Guardar cambios' : 'Registrar contacto'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
