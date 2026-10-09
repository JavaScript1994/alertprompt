import { CreditCard, Info, Trash2 } from 'lucide-react';
import EmptyState from '@/components/shared/EmptyState';
import PageHeader from '@/components/shared/PageHeader';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { useBillingSummary, usePaymentMethods, useRemovePaymentMethod } from '@/hooks/useBilling';
import { useCan } from '@/hooks/usePermissions';

export default function PaymentMethods() {
    const { data: methods, isLoading } = usePaymentMethods();
    const { data: summary } = useBillingSummary();
    const remove = useRemovePaymentMethod();
    const canManage = useCan()('payment_methods.manage');

    return (
        <div>
            <PageHeader title="Métodos de pago" description="Tarjetas guardadas para el cobro de tu membresía." />

            {summary && !summary.cards_enabled && (
                <Alert variant="info" className="mb-6">
                    <Info />
                    <AlertDescription>
                        El pago con tarjeta todavía no está disponible. Mientras tanto puedes pagar por transferencia, Yape o
                        Plin: {summary.transfer_instructions}
                    </AlertDescription>
                </Alert>
            )}

            {isLoading ? (
                <Skeleton className="h-40 w-full rounded-xl" />
            ) : !methods || methods.length === 0 ? (
                <EmptyState
                    icon={CreditCard}
                    title="No tienes tarjetas guardadas"
                    description="Por seguridad, AlertPrompt nunca guarda el número de tu tarjeta: solo una referencia segura de la pasarela."
                />
            ) : (
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    {methods.map((method) => (
                        <Card key={method.id} className="flex-row items-center justify-between gap-4 p-5">
                            <div className="flex items-center gap-3">
                                <CreditCard className="size-6 text-muted-foreground" />
                                <div>
                                    <p className="font-medium capitalize">
                                        {method.brand ?? 'Tarjeta'} •••• {method.last4 ?? '----'}
                                    </p>
                                    {method.exp_month && method.exp_year && (
                                        <p className="text-xs text-muted-foreground">
                                            Vence {String(method.exp_month).padStart(2, '0')}/{method.exp_year}
                                        </p>
                                    )}
                                </div>
                            </div>
                            {canManage && (
                                <Button
                                    variant="ghosterror"
                                    size="icon-sm"
                                    aria-label="Quitar tarjeta"
                                    loading={remove.isPending && remove.variables === method.id}
                                    onClick={() => remove.mutate(method.id)}
                                >
                                    <Trash2 />
                                </Button>
                            )}
                        </Card>
                    ))}
                </div>
            )}
        </div>
    );
}
