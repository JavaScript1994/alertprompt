import { Sparkles } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useMarkAnnouncementsRead } from './api';
import AnnouncementLink from './AnnouncementLink';
import { timeAgo } from './meta';
import { useClientAnnouncements } from './useClientAnnouncements';

/** Al entrar: las novedades que el usuario aún no vio (una vez por usuario). */
export default function WhatsNewDialog() {
    const { items } = useClientAnnouncements();
    const markRead = useMarkAnnouncementsRead();
    const [closed, setClosed] = useState(false);

    const news = items.filter((item) => item.type === 'feature' && !item.read_at).slice(0, 3);
    if (closed || news.length === 0) return null;

    const close = () => {
        markRead.mutate(news.map((item) => item.id));
        setClosed(true);
    };

    return (
        <Dialog open onOpenChange={(open) => !open && close()}>
            <DialogContent className="max-w-lg">
                <DialogHeader>
                    <div className="mb-1 flex size-11 items-center justify-center rounded-full bg-lightprimary">
                        <Sparkles className="size-5 text-primary dark:text-brand-200" />
                    </div>
                    <DialogTitle>Novedades en AlertPrompt</DialogTitle>
                    <DialogDescription>{news.length === 1 ? 'Hay algo nuevo para ti.' : `Hay ${news.length} cosas nuevas para ti.`}</DialogDescription>
                </DialogHeader>
                <ul className="divide-y">
                    {news.map((item) => (
                        <li key={item.id} className="space-y-2 py-3 first:pt-0">
                            <div>
                                <p className="font-semibold text-foreground">{item.title}</p>
                                <p className="text-xs text-muted-foreground">{timeAgo(item.published_at)}</p>
                            </div>
                            <p className="text-sm whitespace-pre-line text-muted-foreground">{item.body}</p>
                            <AnnouncementLink announcement={item} onFollow={close} />
                        </li>
                    ))}
                </ul>
                <DialogFooter>
                    <Button onClick={close}>Entendido</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
