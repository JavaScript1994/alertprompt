import type { UseFormRegisterReturn } from 'react-hook-form';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import type { PersonalKey } from './schemas';

/**
 * Campos de la ficha en una grilla de dos columnas. El formulario que los
 * usa decide el nombre real de cada campo (p. ej. `admin_first_name` en el
 * alta de empresa) a través de `register`.
 */
export default function PersonalDataFields({
    register,
    errors,
    idPrefix,
}: {
    register: (key: PersonalKey) => UseFormRegisterReturn;
    errors: Partial<Record<PersonalKey, string | undefined>>;
    idPrefix: string;
}) {
    const id = (key: PersonalKey) => `${idPrefix}-${key.replace('_', '-')}`;

    return (
        <div className="grid grid-cols-1 gap-x-4 gap-y-4 sm:grid-cols-2">
            <Field label="Nombres" htmlFor={id('first_name')} error={errors.first_name}>
                <Input id={id('first_name')} autoComplete="given-name" aria-invalid={Boolean(errors.first_name)} {...register('first_name')} />
            </Field>
            <Field label="Apellidos" htmlFor={id('last_name')} error={errors.last_name}>
                <Input id={id('last_name')} autoComplete="family-name" aria-invalid={Boolean(errors.last_name)} {...register('last_name')} />
            </Field>
            <Field label="Cargo" htmlFor={id('job_title')} error={errors.job_title}>
                <Input id={id('job_title')} autoComplete="organization-title" placeholder="Opcional" {...register('job_title')} />
            </Field>
            <Field label="Fecha de nacimiento" htmlFor={id('birth_date')} error={errors.birth_date}>
                <Input id={id('birth_date')} type="date" autoComplete="bday" aria-invalid={Boolean(errors.birth_date)} {...register('birth_date')} />
            </Field>
            <Field label="Teléfono" htmlFor={id('phone')} error={errors.phone}>
                <Input id={id('phone')} type="tel" autoComplete="tel" placeholder="01 234 5678" aria-invalid={Boolean(errors.phone)} {...register('phone')} />
            </Field>
            <Field label="Móvil" htmlFor={id('mobile')} error={errors.mobile}>
                <Input id={id('mobile')} type="tel" autoComplete="tel" placeholder="+51 987 654 321" aria-invalid={Boolean(errors.mobile)} {...register('mobile')} />
            </Field>
        </div>
    );
}

/** Texto de finalidad (Ley 29733): para qué se usan los datos opcionales. */
export function PersonalDataNotice() {
    return (
        <p className="text-xs text-muted-foreground">
            Estos datos identifican a la persona dentro de su empresa en AlertPrompt y solo los ven los administradores de esa
            cuenta. Cargo, fecha de nacimiento, teléfonos y foto son opcionales.
        </p>
    );
}
