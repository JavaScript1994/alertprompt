import { Bell, CheckCheck } from 'lucide-react';
import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import { useMarkAllAnnouncementsRead, useMarkAnnouncementsRead } from './api';
import { ANNOUNCEMENT_TYPES, timeAgo } from './meta';
import type { Announcement } from './types';
import { useClientAnnouncements } from './useClientAnnouncements';

/** Campana del encabezado: no leídos y los últimos comunicados. */
export default function NotificationBell() {
    const { items, enabled } = useClientAnnouncements();
    const markRead = useMarkAnnouncementsRead();
    const markAll = useMarkAllAnnouncementsRead();
    const navigate = useNavigate();
    const [open, setOpen] = useState(false);

    if (!enabled) return null;

    const unread = items.filter((item) => !item.read_at).length;

    const openItem = (item: Announcement) => {
        if (!item.read_at) markRead.mutate([item.id]);
        setOpen(false);
        navigate(`/announcements#a${item.id}`);
    };

    return (
        <DropdownMenu open={open} onOpenChange={setOpen}>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" className="relative rounded-full" aria-label={unread > 0 ? `Notificaciones: ${unread} sin leer` : 'Notificaciones'}>
                    <Bell className="size-5" />
                    {unread > 0 && (
                        <span className="absolute top-1 right-1 flex min-w-4 items-center justify-center rounded-full bg-error px-1 text-[0.625rem] leading-4 font-semibold text-white">
                            {unread > 9 ? '9+' : unread}
                        </span>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-[22rem] max-w-[calc(100vw-2rem)] p-0">
                <div className="flex items-center justify-between border-b px-4 py-3">
                    <p className="font-semibold text-foreground">Notificaciones</p>
                    {unread > 0 && (
                        <button type="button" className="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline dark:text-brand-200" onClick={() => markAll.mutate()}>
                            <CheckCheck className="size-3.5" />
                            Marcar todo como leído
                        </button>
                    )}
                </div>
                <ul className="max-h-[22rem] overflow-y-auto">
                    {items.length === 0 && <li className="px-4 py-8 text-center text-sm text-muted-foreground">No tienes notificaciones.</li>}
                    {items.slice(0, 8).map((item) => {
                        const meta = ANNOUNCEMENT_TYPES[item.type];
                        return (
                            <li key={item.id}>
                                <button
                                    type="button"
                                    aria-label={`${meta.label}: ${item.title}`}
                                    onClick={() => openItem(item)}
                                    className={cn('flex w-full gap-3 px-4 py-3 text-left transition-colors hover:bg-muted', !item.read_at && 'bg-lightprimary/40')}
                                >
                                    <span className={cn('flex size-8 shrink-0 items-center justify-center rounded-full', meta.tone)}>
                                        <meta.icon className="size-4" />
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="flex items-center gap-2">
                                            <span className={cn('truncate text-sm text-foreground', !item.read_at && 'font-semibold')}>{item.title}</span>
                                            {!item.read_at && <span className="size-2 shrink-0 rounded-full bg-primary dark:bg-brand-200" aria-label="Sin leer" />}
                                        </span>
                                        <span className="line-clamp-2 text-xs text-muted-foreground">{item.body}</span>
                                        <span className="mt-0.5 block text-[0.7rem] text-muted-foreground">
                                            {meta.label} · {timeAgo(item.published_at)}
                                            {item.type === 'incident' && item.resolved_at && ' · Resuelto'}
                                        </span>
                                    </span>
                                </button>
                            </li>
                        );
                    })}
                </ul>
                <div className="border-t p-2">
                    <Button variant="ghost" size="sm" className="w-full" asChild>
                        <Link to="/announcements" onClick={() => setOpen(false)}>
                            Ver todas
                        </Link>
                    </Button>
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
