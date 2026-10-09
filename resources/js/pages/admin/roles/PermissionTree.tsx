import { ChevronDown } from 'lucide-react';
import { useState } from 'react';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import type { PermissionName, PermissionTreeSection } from '@/types';

type CheckState = boolean | 'indeterminate';

function stateOf(names: PermissionName[], selected: Set<PermissionName>): CheckState {
    const count = names.filter((name) => selected.has(name)).length;
    if (count === 0) return false;
    return count === names.length ? true : 'indeterminate';
}

/**
 * Árbol sección → módulo → permiso con checkboxes de tres estados: marcar
 * una sección o un módulo marca todo lo que cuelga de él.
 */
export default function PermissionTree({
    sections,
    value,
    onChange,
    disabled = false,
}: {
    sections: PermissionTreeSection[];
    value: PermissionName[];
    onChange: (permissions: PermissionName[]) => void;
    disabled?: boolean;
}) {
    const selected = new Set(value);
    const [collapsed, setCollapsed] = useState<Set<string>>(new Set());

    const toggle = (names: PermissionName[], checked: boolean) => {
        const next = new Set(selected);
        for (const name of names) {
            if (checked) next.add(name);
            else next.delete(name);
        }
        onChange([...next].toSorted());
    };

    const toggleCollapsed = (key: string) => {
        setCollapsed((current) => {
            const next = new Set(current);
            if (next.has(key)) next.delete(key);
            else next.add(key);
            return next;
        });
    };

    return (
        <div className="space-y-3">
            {sections.map((section) => {
                const sectionNames = section.modules.flatMap((module) => module.permissions.map((p) => p.name));
                const sectionCount = sectionNames.filter((name) => selected.has(name)).length;
                const isCollapsed = collapsed.has(section.key);
                const sectionId = `section-${section.key}`;

                return (
                    <div key={section.key} className="rounded-lg border bg-card">
                        <div className="flex items-center gap-3 px-4 py-3">
                            <Checkbox
                                id={sectionId}
                                checked={stateOf(sectionNames, selected)}
                                onCheckedChange={(checked) => toggle(sectionNames, checked === true)}
                                disabled={disabled}
                            />
                            <Label htmlFor={sectionId} className="cursor-pointer font-semibold">
                                {section.label}
                            </Label>
                            <span className="ml-auto text-xs text-muted-foreground tabular-nums">
                                {sectionCount}/{sectionNames.length}
                            </span>
                            <button
                                type="button"
                                onClick={() => toggleCollapsed(section.key)}
                                className="rounded-md p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                                aria-label={isCollapsed ? `Expandir ${section.label}` : `Contraer ${section.label}`}
                                aria-expanded={!isCollapsed}
                            >
                                <ChevronDown className={cn('size-4 transition-transform', isCollapsed && '-rotate-90')} />
                            </button>
                        </div>

                        {!isCollapsed && (
                            <ul className="divide-y border-t">
                                {section.modules.map((module) => {
                                    const moduleNames = module.permissions.map((p) => p.name);
                                    const moduleId = `module-${module.key}`;

                                    return (
                                        <li key={module.key} className="px-4 py-3 sm:pl-11">
                                            <div className="flex items-center gap-3">
                                                <Checkbox
                                                    id={moduleId}
                                                    checked={stateOf(moduleNames, selected)}
                                                    onCheckedChange={(checked) => toggle(moduleNames, checked === true)}
                                                    disabled={disabled}
                                                />
                                                <Label htmlFor={moduleId} className="cursor-pointer">
                                                    {module.label}
                                                </Label>
                                            </div>
                                            <div className="mt-2.5 flex flex-wrap gap-x-5 gap-y-2 pl-7">
                                                {module.permissions.map((permission) => {
                                                    const permissionId = `permission-${permission.name}`;

                                                    return (
                                                        <div key={permission.name} className="flex items-center gap-2">
                                                            <Checkbox
                                                                id={permissionId}
                                                                checked={selected.has(permission.name)}
                                                                onCheckedChange={(checked) =>
                                                                    toggle([permission.name], checked === true)
                                                                }
                                                                disabled={disabled}
                                                            />
                                                            <Label
                                                                htmlFor={permissionId}
                                                                className="cursor-pointer font-normal text-muted-foreground"
                                                            >
                                                                {permission.label}
                                                            </Label>
                                                        </div>
                                                    );
                                                })}
                                            </div>
                                        </li>
                                    );
                                })}
                            </ul>
                        )}
                    </div>
                );
            })}
        </div>
    );
}
