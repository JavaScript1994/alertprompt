import { ArrowLeft, Ban, LogIn, Pencil, RotateCcw } from 'lucide-react';
import { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import ConfirmDialog from '@/components/shared/ConfirmDialog';
import PageHeader from '@/components/shared/PageHeader';
import Tabs, { type TabItem } from '@/components/shared/Tabs';
import { Alert, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { useClient, useImpersonation, useSetClientStatus } from '@/hooks/useClients';
import { useCan } from '@/hooks/usePermissions';
import { apiErrorMessage, formatDateTime } from '@/lib/format';
import type { Client } from '@/types';
import ClientBillingTab from './ClientBillingTab';
import ClientChannelsTab from './ClientChannelsTab';
import ClientFormDialog from './ClientFormDialog';
import ClientMembershipTab from './ClientMembershipTab';
import ClientModulesTab from './ClientModulesTab';
import { ClientActivityTab, ClientCampaignsTab, ClientContactsTab, ClientTemplatesTab, ClientUsersTab } from './ClientSupervision';
import { CLIENT_STATUS, CLIENT_TYPE_LABELS, DOCUMENT_LABELS } from './clientLabels';

type TabKey = 'summary' | 'users' | 'membership' | 'billing' | 'modules' | 'channels' | 'contacts' | 'templates' | 'campaigns' | 'activity';

function Summary({ client }: { client: Client }) {
    const rows: [string, string | null][] = [
        ['Tipo', CLIENT_TYPE_LABELS[client.type].singular],
        ['Documento', client.document_type ? `${DOCUMENT_LABELS[client.document_type]} ${client.document_number}` : null],
        ['Correo de contacto', client.contact_email],
        ['Teléfono', client.contact_phone],
        ['Dirección', client.address],
        ['Plan', client.plan_name],
        ['Cliente desde', formatDateTime(client.created_at)],
    ];

    return (
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <Card className="lg:col-span-2">
                <CardHeader>
                    <CardTitle>Datos del cliente</CardTitle>
                </CardHeader>
                <CardContent>
                    <dl className="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                        {rows.map(([label, value]) => (
                            <div key={label}>
                                <dt className="text-xs text-muted-foreground">{label}</dt>
                                <dd className="mt-0.5 text-sm font-medium text-foreground">{value || '—'}</dd>
                            </div>
                        ))}
                    </dl>
                </CardContent>
            </Card>
            <div className="grid grid-cols-3 gap-4 lg:grid-cols-1">
                {[
                    ['Usuarios', client.users_count],
                    ['Contactos', client.contacts_count],
                    ['Campañas', client.campaigns_count],
                ].map(([label, value]) => (
                    <Card key={label} className="p-5">
                        <p className="text-xs text-muted-foreground">{label}</p>
                        <p className="mt-1 text-2xl font-semibold tabular-nums">{Number(value).toLocaleString('es-PE')}</p>
                    </Card>
                ))}
            </div>
        </div>
    );
}

export default function ClientDetail() {
    const clientId = Number(useParams<{ id: string }>().id);
    const { data: client, isLoading } = useClient(clientId);
    const setStatus = useSetClientStatus(clientId);
    const { start: impersonate } = useImpersonation();
    const can = useCan();
    const navigate = useNavigate();
    const [tab, setTab] = useState<TabKey>('summary');
    const [isEditing, setIsEditing] = useState(false);
    const [confirmingStatus, setConfirmingStatus] = useState(false);
    const [reason, setReason] = useState('');

    if (isLoading || !client) {
        return <Skeleton className="h-96 w-full rounded-xl" />;
    }

    const isSuspended = client.status === 'suspended';
    const tabs: TabItem<TabKey>[] = [
        { key: 'summary', label: 'Resumen' },
        { key: 'users', label: 'Usuarios' },
        ...(can('admin.memberships.view') ? ([{ key: 'membership', label: 'Membresía' }] as TabItem<TabKey>[]) : []),
        ...(can('admin.billing.view') ? ([{ key: 'billing', label: 'Facturación' }] as TabItem<TabKey>[]) : []),
        ...(can('admin.modules.view') ? ([{ key: 'modules', label: 'Módulos' }] as TabItem<TabKey>[]) : []),
        { key: 'channels', label: 'Canales' },
        ...(can('admin.supervision.view')
            ? ([
                  { key: 'contacts', label: 'Contactos' },
                  { key: 'templates', label: 'Plantillas' },
                  { key: 'campaigns', label: 'Campañas' },
              ] as TabItem<TabKey>[])
            : []),
        { key: 'activity', label: 'Actividad' },
    ];
    const backTo = client.type === 'company' ? '/admin/clients/companies' : '/admin/clients/individuals';

    const enterPanel = () => impersonate.mutate(client.id, { onSuccess: () => navigate('/') });

    return (
        <div>
            <PageHeader
                title={client.name}
                description={
                    <span className="inline-flex flex-wrap items-center gap-3">
                        <Link to={backTo} className="inline-flex items-center gap-1 hover:text-foreground">
                            <ArrowLeft className="size-3.5" />
                            {CLIENT_TYPE_LABELS[client.type].plural}
                        </Link>
                        <Badge variant={CLIENT_STATUS[client.status].variant}>{CLIENT_STATUS[client.status].label}</Badge>
                    </span>
                }
                actions={
                    <>
                        {can('admin.clients.update') && (
                            <Button variant="outline" onClick={() => setIsEditing(true)}>
                                <Pencil />
                                Editar
                            </Button>
                        )}
                        {can('admin.clients.suspend') && (
                            <Button
                                variant="outline"
                                className={isSuspended ? '' : 'text-error hover:text-error'}
                                onClick={() => setConfirmingStatus(true)}
                            >
                                {isSuspended ? <RotateCcw /> : <Ban />}
                                {isSuspended ? 'Reactivar' : 'Suspender'}
                            </Button>
                        )}
                        {can('admin.impersonate.use') && (
                            <Button onClick={enterPanel} loading={impersonate.isPending}>
                                <LogIn />
                                Entrar al panel
                            </Button>
                        )}
                    </>
                }
            />

            {impersonate.isError && (
                <Alert variant="error" className="mb-4">
                    <AlertTitle>{apiErrorMessage(impersonate.error, [], 'No se pudo entrar al panel del cliente.')}</AlertTitle>
                </Alert>
            )}

            <Tabs tabs={tabs} active={tab} onChange={setTab} />

            {tab === 'summary' && <Summary client={client} />}
            {tab === 'users' && <ClientUsersTab clientId={client.id} />}
            {tab === 'membership' && <ClientMembershipTab clientId={client.id} />}
            {tab === 'billing' && <ClientBillingTab clientId={client.id} />}
            {tab === 'modules' && <ClientModulesTab clientId={client.id} />}
            {tab === 'channels' && <ClientChannelsTab clientId={client.id} />}
            {tab === 'contacts' && <ClientContactsTab clientId={client.id} />}
            {tab === 'templates' && <ClientTemplatesTab clientId={client.id} />}
            {tab === 'campaigns' && <ClientCampaignsTab clientId={client.id} />}
            {tab === 'activity' && <ClientActivityTab clientId={client.id} />}

            <ClientFormDialog open={isEditing} type={client.type} client={client} onClose={() => setIsEditing(false)} />

            <ConfirmDialog
                open={confirmingStatus}
                title={isSuspended ? 'Reactivar cliente' : 'Suspender cliente'}
                description={
                    isSuspended
                        ? `Los usuarios de ${client.name} podrán volver a iniciar sesión.`
                        : `Los usuarios de ${client.name} no podrán iniciar sesión y se cortarán sus sesiones abiertas. Sus datos se conservan.`
                }
                confirmLabel={isSuspended ? 'Reactivar' : 'Suspender'}
                destructive={!isSuspended}
                loading={setStatus.isPending}
                onConfirm={() =>
                    setStatus.mutate(
                        { suspend: !isSuspended, reason: reason || undefined },
                        {
                            onSuccess: () => {
                                setConfirmingStatus(false);
                                setReason('');
                            },
                        },
                    )
                }
                onCancel={() => setConfirmingStatus(false)}
            >
                {!isSuspended && (
                    <Field label="Motivo (opcional)" htmlFor="suspend-reason">
                        <Input id="suspend-reason" value={reason} onChange={(event) => setReason(event.target.value)} />
                    </Field>
                )}
            </ConfirmDialog>
        </div>
    );
}
