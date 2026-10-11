import { Camera, Trash2 } from 'lucide-react';
import { useId, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import UserAvatar from './UserAvatar';
import { photoError } from './schemas';

/**
 * Foto de ficha. Dos usos:
 * - `onUpload`/`onRemove`: sube al elegir (Mi perfil, Usuarios).
 * - `onFileChange`: guarda el archivo para enviarlo con el formulario (alta de empresa).
 */
export default function PhotoField({
    name,
    photoUrl,
    onUpload,
    onRemove,
    onFileChange,
    busy = false,
    error,
    layout = 'row',
    size = 'md',
}: {
    name: string;
    photoUrl: string | null;
    onUpload?: (file: File) => void;
    onRemove?: () => void;
    onFileChange?: (file: File | null) => void;
    busy?: boolean;
    error?: string | null;
    /** `stacked`: foto arriba y botones debajo, para columnas angostas. */
    layout?: 'row' | 'stacked';
    size?: 'sm' | 'md' | 'lg';
}) {
    const inputId = useId();
    const input = useRef<HTMLInputElement>(null);
    const [preview, setPreview] = useState<string | null>(null);
    const [localError, setLocalError] = useState<string | null>(null);

    const shown = preview ?? photoUrl;

    const pick = (file: File | undefined) => {
        if (input.current) input.current.value = '';
        if (!file) return;

        const problem = photoError(file);
        setLocalError(problem);
        if (problem) return;

        if (onFileChange) {
            if (preview) URL.revokeObjectURL(preview);
            setPreview(URL.createObjectURL(file));
            onFileChange(file);
        } else {
            onUpload?.(file);
        }
    };

    const remove = () => {
        if (preview) URL.revokeObjectURL(preview);
        setPreview(null);
        setLocalError(null);
        if (onFileChange) onFileChange(null);
        else onRemove?.();
    };

    const message = localError ?? error;

    const stacked = layout === 'stacked';
    const avatarSize = { sm: 'size-16', md: 'size-20', lg: 'size-28' }[size];

    return (
        <div className={cn('flex gap-4', stacked ? 'flex-col items-center text-center' : 'items-center')}>
            <UserAvatar name={name} photoUrl={shown} className={avatarSize} fallbackClassName={size === 'lg' ? 'text-2xl' : 'text-lg'} />
            <div className="space-y-1.5">
                <div className={cn('flex flex-wrap gap-2', stacked && 'justify-center')}>
                    <Button type="button" variant="outline" size="sm" loading={busy} onClick={() => input.current?.click()}>
                        {!busy && <Camera />}
                        {shown ? 'Cambiar foto' : 'Subir foto'}
                    </Button>
                    {shown && (
                        <Button type="button" variant="ghosterror" size="sm" disabled={busy} onClick={remove}>
                            <Trash2 />
                            Quitar
                        </Button>
                    )}
                </div>
                <p className={message ? 'text-xs text-error' : 'text-xs text-muted-foreground'}>
                    {message ?? 'JPG, PNG o WebP, hasta 2 MB. Opcional.'}
                </p>
                <input
                    ref={input}
                    id={inputId}
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    className="sr-only"
                    aria-label="Foto"
                    onChange={(event) => pick(event.target.files?.[0])}
                />
            </div>
        </div>
    );
}
