import { ArrowRight, Megaphone, MessageSquareText, Send, Users, type LucideIcon } from 'lucide-react';
import { Link } from 'react-router-dom';
import ChannelBadge from '@/components/shared/ChannelBadge';
import EmptyState from '@/components/shared/EmptyState';
import { CampaignStatusBadge } from '@/components/shared/StatusBadge';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { useAuthUser } from '@/hooks/useAuth';
import { useCampaigns } from '@/hooks/useCampaigns';
import { useContacts } from '@/hooks/useContacts';
import { initials } from '@/lib/format';

// Tarjetas de métricas al estilo "TopCards" de la plantilla: fondo tenue del
// color del estado y valor destacado con el mismo color.
function StatCard({
    icon: Icon,
    label,
    value,
    to,
    tone,
}: {
    icon: LucideIcon;
    label: string;
    value: string | number | undefined;
    to: string;
    tone: 'primary' | 'secondary' | 'success' | 'info';
}) {
    const tones = {
        primary: 'bg-lightprimary text-primary dark:text-brand-200',
        secondary: 'bg-lightsecondary text-secondary',
        success: 'bg-lightsuccess text-success',
        info: 'bg-lightinfo text-info',
    } as const;

    return (
        <Link
            to={to}
            className={`group rounded-xl p-5 text-center transition-transform hover:scale-[1.02] ${tones[tone]}`}
        >
            <div className="mx-auto mb-3 flex size-12 items-center justify-center rounded-full bg-card/70 dark:bg-card/30">
                <Icon className="size-6" strokeWidth={1.75} />
            </div>
            <p className="mb-1 font-semibold">{label}</p>
            {value === undefined ? (
                <Skeleton className="mx-auto h-6 w-12 bg-card/60" />
            ) : (
                <p className="text-xl font-semibold">{value.toLocaleString('es-PE')}</p>
            )}
        </Link>
    );
}

export default function Dashboard() {
    const { data: user } = useAuthUser();
    const { data: campaigns } = useCampaigns(1);
    const { data: contacts } = useContacts({ page: 1, search: '' });

    const recent = campaigns?.data.slice(0, 5) ?? [];
    const runningCount = campaigns?.data.filter((c) => c.status === 'running').length;
    const delivered = campaigns?.data.reduce((sum, c) => sum + c.stats.delivered + c.stats.read, 0);

    return (
        <div className="grid grid-cols-12 gap-6">
            <div className="col-span-12 flex items-center gap-4 rounded-xl bg-lightsecondary p-6">
                <Avatar className="size-12">
                    <AvatarFallback className="bg-card text-sm">{initials(user?.name)}</AvatarFallback>
                </Avatar>
                <div className="min-w-0">
                    <h1 className="text-lg">¡Hola de nuevo, {user?.name?.split(' ')[0] ?? ''}! 👋</h1>
                    <p className="text-muted-foreground">Así está {user?.tenant.name ?? 'tu cuenta'} hoy.</p>
                </div>
            </div>

            <div className="col-span-12 grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard icon={Users} label="Contactos" value={contacts?.meta.total} to="/contacts" tone="primary" />
                <StatCard icon={Megaphone} label="Campañas activas" value={runningCount} to="/campaigns" tone="success" />
                <StatCard icon={Send} label="Mensajes entregados" value={delivered} to="/campaigns" tone="secondary" />
                <StatCard
                    icon={MessageSquareText}
                    label="Total campañas"
                    value={campaigns?.meta.total}
                    to="/campaigns"
                    tone="info"
                />
            </div>

            <Card className="col-span-12 gap-0 p-0">
                <CardHeader className="flex-row items-center justify-between border-b px-6 py-4">
                    <div className="flex items-center gap-3">
                        <CardTitle className="text-base">Campañas recientes</CardTitle>
                        <span className="flex items-center gap-1.5 text-xs text-muted-foreground">
                            <span className="size-1.5 animate-pulse rounded-full bg-whatsapp-500" />
                            en vivo
                        </span>
                    </div>
                    <Button asChild variant="link" size="sm">
                        <Link to="/campaigns">
                            Ver todas <ArrowRight />
                        </Link>
                    </Button>
                </CardHeader>

                {recent.length === 0 ? (
                    <div className="p-6">
                        <EmptyState
                            icon={Megaphone}
                            title="Todavía no hay campañas"
                            description="Creá una plantilla aprobada y lanzá tu primera campaña."
                            action={
                                <Button asChild size="sm">
                                    <Link to="/campaigns">Crear campaña</Link>
                                </Button>
                            }
                        />
                    </div>
                ) : (
                    <ul className="divide-y">
                        {recent.map((campaign) => (
                            <li key={campaign.id}>
                                <Link
                                    to="/campaigns"
                                    className="flex items-center justify-between gap-4 px-6 py-4 transition-colors hover:bg-muted/50"
                                >
                                    <div className="flex min-w-0 items-center gap-3">
                                        <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-lightprimary text-primary dark:text-brand-200">
                                            <Megaphone className="size-5" strokeWidth={1.75} />
                                        </div>
                                        <div className="min-w-0">
                                            <p className="truncate font-medium text-foreground">{campaign.name}</p>
                                            <ChannelBadge channel={campaign.channel} className="mt-1" />
                                        </div>
                                    </div>
                                    <div className="flex shrink-0 items-center gap-4">
                                        <span className="hidden text-xs text-muted-foreground sm:inline">
                                            {campaign.stats.delivered + campaign.stats.read} / {campaign.stats.total}{' '}
                                            entregados
                                        </span>
                                        <CampaignStatusBadge status={campaign.status} />
                                    </div>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </Card>
        </div>
    );
}
