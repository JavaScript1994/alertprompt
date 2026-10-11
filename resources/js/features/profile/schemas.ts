import { z } from 'zod';

const optional = z
    .string()
    .trim()
    .transform((value) => (value === '' ? null : value));

const phone = optional.pipe(
    z
        .string()
        .regex(/^\+?[0-9 ()-]{6,20}$/, 'Usa solo números, espacios, guiones y el + inicial')
        .nullable(),
);

/** Fecha de nacimiento: opcional, mínimo 16 años (igual que el backend). */
const birthDate = optional.pipe(
    z
        .string()
        .refine((value) => {
            const limit = new Date();
            limit.setFullYear(limit.getFullYear() - 16);
            return value >= '1900-01-01' && new Date(`${value}T00:00:00`) < limit;
        }, 'La persona debe tener al menos 16 años')
        .nullable(),
);

export const personalDataShape = {
    first_name: z.string().trim().min(1, 'Ingresa los nombres').max(80),
    last_name: z.string().trim().min(1, 'Ingresa los apellidos').max(80),
    job_title: optional.pipe(z.string().max(100).nullable()),
    birth_date: birthDate,
    phone,
    mobile: phone,
};

export const personalDataSchema = z.object(personalDataShape);
export type PersonalDataInput = z.input<typeof personalDataSchema>;
export type PersonalDataOutput = z.output<typeof personalDataSchema>;

export const PERSONAL_KEYS = ['first_name', 'last_name', 'job_title', 'birth_date', 'phone', 'mobile'] as const;
export type PersonalKey = (typeof PERSONAL_KEYS)[number];

/** Valores iniciales del formulario a partir de un usuario. */
export function personalDefaults(user?: Partial<Record<PersonalKey, string | null>>): PersonalDataInput {
    return {
        first_name: user?.first_name ?? '',
        last_name: user?.last_name ?? '',
        job_title: user?.job_title ?? '',
        birth_date: user?.birth_date ?? '',
        phone: user?.phone ?? '',
        mobile: user?.mobile ?? '',
    };
}

export const PHOTO_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
export const PHOTO_MAX_BYTES = 2 * 1024 * 1024;

/** Validación previa de la foto (el backend vuelve a validar tamaño y dimensiones). */
export function photoError(file: File): string | null {
    if (!PHOTO_TYPES.includes(file.type)) return 'Usa una imagen JPG, PNG o WebP.';
    if (file.size > PHOTO_MAX_BYTES) return 'La foto no puede pesar más de 2 MB.';
    return null;
}
