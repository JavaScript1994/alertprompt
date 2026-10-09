import { Blocks } from 'lucide-react';
import EmptyState from '@/components/shared/EmptyState';
import PageHeader from '@/components/shared/PageHeader';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { useModuleCatalog } from '@/hooks/useModulesAdmin';

/** Catálogo de módulos: qué incluye cada plan y cuántos clientes lo usan. */
export default function Modules() {
    const { data: modules, isLoading } = useModuleCatalog();

    return (
        <div>
            <PageHeader
                title="Módulos"
                description="Capacidades que se activan por cliente. Se activan desde la ficha de cada cliente, pestaña Módulos."
            />

            {isLoading ? (
                <Skeleton className="h-64 w-full rounded-xl" />
            ) : !modules || modules.length === 0 ? (
                <EmptyState icon={Blocks} title="No hay módulos configurados" />
            ) : (
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {modules.map((module) => (
                        <Card key={module.key} className="gap-3 p-5">
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <p className="font-semibold text-foreground">{module.label}</p>
                                    <p className="mt-1 text-sm text-muted-foreground">{module.description}</p>
                                </div>
                                <div className="shrink-0 text-right">
                                    <p className="text-2xl font-semibold tabular-nums">{module.clients_count}</p>
                                    <p className="text-xs text-muted-foreground">clientes</p>
                                </div>
                            </div>
                            <div className="flex flex-wrap items-center gap-1.5">
                                <span className="text-xs text-muted-foreground">Incluido en:</span>
                                {module.included_in_plans.length > 0 ? (
                                    module.included_in_plans.map((plan) => (
                                        <Badge key={plan} variant="outline" className="capitalize">
                                            {plan}
                                        </Badge>
                                    ))
                                ) : (
                                    <span className="text-xs text-muted-foreground">ningún plan (solo a pedido)</span>
                                )}
                            </div>
                        </Card>
                    ))}
                </div>
            )}
        </div>
    );
}
