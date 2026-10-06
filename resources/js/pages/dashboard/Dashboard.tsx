import { Megaphone, MessageSquareText, Send, Users } from 'lucide-react';
import { Link } from 'react-router-dom';
import Badge, { type BadgeVariant } from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import ChannelBadge from '@/components/ui/ChannelBadge';
import EmptyState from '@/components/ui/EmptyState';
import PageHeader from '@/components/ui/PageHeader';
import { useAuthUser } from '@/hooks/useAuth';
import { useCampaigns } from '@/hooks/useCampaigns';
import { useContacts } from '@/hooks/useContacts';
import type { Campaign } from '@/types';

const STATUS_LABELS: Record<Campaign['status'], string> = {
    draft: 'Borrador',
    scheduled: 'Programada',
    running: 'En curso',
    paused: 'Pausada',
    completed: 'Completada',
    cancelled: 'Cancelada',
};

const STATUS_VARIANTS: Record<Campaign['status'], BadgeVariant> = {
    draft: 'neutral',
    scheduled: 'info',
    running: 'success',
    paused: 'warning',
    completed: 'brand',
    cancelled: 'error',
};

function StatCard({ icon: Icon, label, value }: { icon: typeof Users; label: string; value: string | number }) {
    return (
        <div className="rounded-xl border border-slate-200 bg-white p-5">
            <div className="flex items-center gap-3">
                <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-prompt-50">
                    <Icon className="h-4.5 w-4.5 text-prompt-600" strokeWidth={2} />
                </div>
                <div>
                    <p className="text-xs font-medium text-slate-500">{label}</p>
                    <p className="text-xl font-semibold text-ink-900">{value}</p>
                </div>
            </div>
        </div>
    );
}

export default function Dashboard() {
    const { data: user } = useAuthUser();
    const { data: campaigns } = useCampaigns(1);
    const { data: contacts } = useContacts({ page: 1, search: '' });

    const recent = campaigns?.data.slice(0, 5) ?? [];
    const runningCount = campaigns?.data.filter((c) => c.status === 'running').length ?? 0;
    const delivered = campaigns?.data.reduce((sum, c) => sum + c.stats.delivered + c.stats.read, 0) ?? 0;

    return (
        <div>
            <PageHeader title={`Hola, ${user?.name?.split(' ')[0] ?? ''}`} description="Así está tu cuenta hoy." />

            <div className="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard icon={Users} label="Contactos" value={contacts?.meta.total ?? '—'} />
                <StatCard icon={Megaphone} label="Campañas activas" value={runningCount} />
                <StatCard icon={Send} label="Mensajes entregados" value={delivered} />
                <StatCard icon={MessageSquareText} label="Total campañas" value={campaigns?.meta.total ?? '—'} />
            </div>

            <div className="rounded-xl border border-slate-200 bg-white">
                <div className="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                    <h2 className="text-sm font-semibold text-ink-900">Campañas recientes</h2>
                    <div className="flex items-center gap-3">
                        <span className="flex items-center gap-1.5 text-xs text-slate-400">
                            <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-whatsapp-500" />
                            en vivo
                        </span>
                        <Link to="/campaigns" className="text-xs font-medium text-brand-600 hover:text-brand-500">
                            Ver todas
                        </Link>
                    </div>
                </div>

                {recent.length === 0 ? (
                    <div className="p-5">
                        <EmptyState
                            icon={Megaphone}
                            title="Todavía no hay campañas"
                            description="Creá una plantilla aprobada y lanzá tu primera campaña."
                            action={
                                <Link to="/campaigns">
                                    <Button size="sm">Crear campaña</Button>
                                </Link>
                            }
                        />
                    </div>
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {recent.map((campaign) => (
                            <li key={campaign.id}>
                                <Link
                                    to="/campaigns"
                                    className="flex items-center justify-between gap-4 px-5 py-3.5 hover:bg-slate-50"
                                >
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-medium text-ink-900">{campaign.name}</p>
                                        <div className="mt-1">
                                            <ChannelBadge channel={campaign.channel} />
                                        </div>
                                    </div>
                                    <div className="flex shrink-0 items-center gap-4">
                                        <span className="text-xs text-slate-500">
                                            {campaign.stats.delivered + campaign.stats.read} / {campaign.stats.total}{' '}
                                            entregados
                                        </span>
                                        <Badge variant={STATUS_VARIANTS[campaign.status]}>
                                            {STATUS_LABELS[campaign.status]}
                                        </Badge>
                                    </div>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </div>
    );
}
