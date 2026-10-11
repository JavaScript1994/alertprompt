import { X } from 'lucide-react';
import { cn } from '@/lib/utils';
import { useDismissAnnouncement } from './api';
import AnnouncementLink from './AnnouncementLink';
import { ANNOUNCEMENT_TYPES } from './meta';
import { useClientAnnouncements } from './useClientAnnouncements';

/**
 * Franjas bajo el encabezado: incidentes abiertos (no se cierran: duran
 * hasta que la plataforma los resuelve) y acciones requeridas (se cierran).
 */
export default function AnnouncementBanners() {
    const { items } = useClientAnnouncements();
    const dismiss = useDismissAnnouncement();

    const banners = items.filter(
        (item) => (item.type === 'incident' && !item.resolved_at) || (item.type === 'action_required' && !item.dismissed_at),
    );
    if (banners.length === 0) return null;

    return (
        <div className="space-y-px">
            {banners.slice(0, 3).map((item) => {
                const meta = ANNOUNCEMENT_TYPES[item.type];
                const incident = item.type === 'incident';
                return (
                    <div
                        key={item.id}
                        role={incident ? 'alert' : 'status'}
                        className={cn('flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-2.5 text-sm sm:px-6', incident ? 'bg-lighterror' : 'bg-lightwarning')}
                    >
                        <meta.icon className={cn('size-4 shrink-0', incident ? 'text-error' : 'text-warning')} />
                        <p className="min-w-0 flex-1 text-foreground">
                            <span className="font-semibold">{item.title}.</span> <span className="text-muted-foreground">{item.body.split('\n')[0]}</span>
                        </p>
                        <AnnouncementLink announcement={item} />
                        {!incident && (
                            <button
                                type="button"
                                aria-label="Cerrar aviso"
                                className="rounded-full p-1 text-muted-foreground hover:bg-black/5 hover:text-foreground dark:hover:bg-white/10"
                                onClick={() => dismiss.mutate(item.id)}
                            >
                                <X className="size-4" />
                            </button>
                        )}
                    </div>
                );
            })}
        </div>
    );
}
