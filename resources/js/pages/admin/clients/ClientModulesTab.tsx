import { CheckCircle2 } from 'lucide-react';
import { useState } from 'react';
import { Alert, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import { useClientModules, useModuleCatalog, useUpdateClientModules } from '@/hooks/useModulesAdmin';
import { useCan } from '@/hooks/usePermissions';
import { apiErrorMessage } from '@/lib/format';
import type { ModuleKey } from '@/types';

export default function ClientModulesTab({ clientId }: { clientId: number }) {
    const { data: catalog, isLoading: isCatalogLoading } = useModuleCatalog();
    const { data: enabled, isLoading } = useClientModules(clientId);
    const update = useUpdateClientModules(clientId);
    const canManage = useCan()('admin.modules.manage');
    // null = sin cambios locales todavía: se muestra lo guardado.
    const [draft, setDraft] = useState<ModuleKey[] | null>(null);

    if (isLoading || isCatalogLoading || !catalog || !enabled) return <Skeleton className="h-64 w-full rounded-xl" />;

    const selected = draft ?? enabled;
    const isDirty = draft !== null && draft.toSorted().join() !== enabled.toSorted().join();

    const toggle = (module: ModuleKey, checked: boolean) =>
        setDraft(checked ? [...selected, module] : selected.filter((key) => key !== module));

    return (
        <div className="space-y-4">
            {update.isSuccess && !isDirty && (
                <Alert variant="success">
                    <CheckCircle2 />
                    <AlertTitle>Módulos actualizados. El cliente los verá al recargar su panel.</AlertTitle>
                </Alert>
            )}
            {update.isError && (
                <Alert variant="error">
                    <AlertTitle>{apiErrorMessage(update.error, ['modules'], 'No se pudieron guardar los módulos.')}</AlertTitle>
                </Alert>
            )}

            <div className="divide-y rounded-xl border bg-card shadow-md">
                {catalog.map((module) => {
                    const id = `module-${module.key}`;
                    return (
                        <div key={module.key} className="flex items-start gap-3 px-5 py-4">
                            <Checkbox
                                id={id}
                                className="mt-0.5"
                                checked={selected.includes(module.key)}
                                onCheckedChange={(checked) => toggle(module.key, checked === true)}
                                disabled={!canManage}
                            />
                            <div>
                                <Label htmlFor={id} className="cursor-pointer font-semibold">
                                    {module.label}
                                </Label>
                                <p className="mt-1 text-sm text-muted-foreground">{module.description}</p>
                            </div>
                        </div>
                    );
                })}
            </div>

            {canManage && (
                <div className="flex gap-2">
                    <Button
                        disabled={!isDirty}
                        loading={update.isPending}
                        onClick={() => draft && update.mutate(draft, { onSuccess: () => setDraft(null) })}
                    >
                        Guardar módulos
                    </Button>
                    {isDirty && (
                        <Button variant="outline" onClick={() => setDraft(null)}>
                            Descartar
                        </Button>
                    )}
                </div>
            )}
        </div>
    );
}
