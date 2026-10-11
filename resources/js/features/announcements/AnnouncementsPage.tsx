import { CheckCheck, Inbox } from 'lucide-react';
import { useState } from 'react';
import EmptyState from '@/components/shared/EmptyState';
import PageHeader from '@/components/shared/PageHeader';
import Tabs from '@/components/shared/Tabs';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import { useMarkAllAnnouncementsRead, useMarkAnnouncementsRead } from './api';
import AnnouncementLink from './AnnouncementLink';
import { ANNOUNCEMENT_TYPES, TYPE_ORDER } from './meta';
import type { AnnouncementType } from './types';
import { useClientAnnouncements } from './useClientAnnouncements';

type Filter = 'all' | AnnouncementType;

/** Novedades: historial de comunicados de AlertPrompt para el usuario. */
export default function AnnouncementsPage() {
    const { items, isLoading } = useClientAnnouncements();
    const markRead = useMarkAnnouncementsRead();
    const markAll = useMarkAllAnnouncementsRead();
    const [filter, setFilter] = useState<Filter>('all');

    const unread = items.filter((item) => !item.read_at).length;
    const visible = filter === 'all' ? items : items.filter((item) => item.type === filter);
    const present = TYPE_ORDER.filter((type) => items.some((item) => item.type === type));

    return (
        <div>
            <PageHeader
                title="Novedades"
                description="Comunicados de AlertPrompt: nuevas funciones, avisos e incidentes."
                actions={
                    unread > 0 && (
                        <Button variant="outline" loading={markAll.isPending} onClick={() => markAll.mutate()}>
                            <CheckCheck />
                            Marcar todo como leído
                        </Button>
                    )
                }
            />

            {present.length > 1 && (
                <Tabs
                    tabs={[{ key: 'all' as Filter, label: 'Todas' }, ...present.map((type) => ({ key: type as Filter, label: ANNOUNCEMENT_TYPES[type].label }))]}
                    active={filter}
                    onChange={setFilter}
                />
            )}

            {isLoading ? (
                <Skeleton className="h-64 w-full rounded-xl" />
            ) : visible.length === 0 ? (
                <EmptyState icon={Inbox} title="No hay comunicados" description="Aquí verás las novedades y avisos de AlertPrompt." />
            ) : (
                <div className="space-y-4">
                    {visible.map((item) => {
                        const meta = ANNOUNCEMENT_TYPES[item.type];
                        return (
                            <Card
                                key={item.id}
                                id={`a${item.id}`}
                                className={cn('scroll-mt-24 gap-3', !item.read_at && 'border-primary/40 dark:border-brand-200/40')}
                                onMouseEnter={() => !item.read_at && !markRead.isPending && markRead.mutate([item.id])}
                            >
                                <div className="flex items-start gap-3">
                                    <span className={cn('flex size-10 shrink-0 items-center justify-center rounded-full', meta.tone)}>
                                        <meta.icon className="size-5" />
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Badge variant={meta.badge}>{meta.label}</Badge>
                                            {item.type === 'incident' && (item.resolved_at ? <Badge variant="success">Resuelto</Badge> : <Badge variant="error">En curso</Badge>)}
                                            {!item.read_at && <Badge variant="primary">Nuevo</Badge>}
                                            <span className="text-xs text-muted-foreground">{formatDateTime(item.published_at)}</span>
                                        </div>
                                        <h2 className="mt-1.5 text-lg font-semibold text-foreground">{item.title}</h2>
                                        <p className="mt-1 text-sm whitespace-pre-line text-muted-foreground">{item.body}</p>
                                        <div className="mt-3">
                                            <AnnouncementLink announcement={item} />
                                        </div>
                                    </div>
                                </div>
                            </Card>
                        );
                    })}
                </div>
            )}
        </div>
    );
}
