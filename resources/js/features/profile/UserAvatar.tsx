import { UserRound } from 'lucide-react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { initials } from '@/lib/format';

/** Foto del usuario o, sin foto (o mientras carga), sus iniciales; sin nombre aún, un ícono. */
export default function UserAvatar({
    name,
    photoUrl,
    className,
    fallbackClassName,
}: {
    name: string | null | undefined;
    photoUrl: string | null | undefined;
    className?: string;
    fallbackClassName?: string;
}) {
    return (
        <Avatar className={className}>
            {photoUrl && <AvatarImage src={photoUrl} alt={name ?? ''} />}
            <AvatarFallback className={fallbackClassName}>
                {name?.trim() ? initials(name) : <UserRound className="size-1/2" strokeWidth={1.75} aria-hidden />}
            </AvatarFallback>
        </Avatar>
    );
}
