import { ArrowRight, ExternalLink } from 'lucide-react';
import { Link } from 'react-router-dom';
import { Button } from '@/components/ui/button';
import type { Announcement } from './types';

/** Botón del comunicado: ruta del panel o enlace externo (https) en otra pestaña. */
export default function AnnouncementLink({
    announcement,
    onFollow,
    size = 'sm',
}: {
    announcement: Pick<Announcement, 'link_url' | 'link_label'>;
    onFollow?: () => void;
    size?: 'sm' | 'default';
}) {
    const { link_url: url, link_label: label } = announcement;
    if (!url) return null;

    if (url.startsWith('/')) {
        return (
            <Button variant="outline" size={size} asChild>
                <Link to={url} onClick={onFollow}>
                    {label ?? 'Ver más'}
                    <ArrowRight />
                </Link>
            </Button>
        );
    }

    return (
        <Button variant="outline" size={size} asChild>
            <a href={url} target="_blank" rel="noopener noreferrer" onClick={onFollow}>
                {label ?? 'Ver más'}
                <ExternalLink />
            </a>
        </Button>
    );
}
