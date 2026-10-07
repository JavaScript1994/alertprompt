import { MessageSquareText, ShieldCheck, Zap } from 'lucide-react';
import { Outlet } from 'react-router-dom';
import Logo from '@/components/shared/Logo';
import { Card } from '@/components/ui/card';

const FEATURES = [
    { icon: MessageSquareText, label: 'WhatsApp, SMS y Email' },
    { icon: ShieldCheck, label: 'Cumplimiento Ley N° 32323' },
    { icon: Zap, label: 'Entregas en vivo' },
];

/** Layout "auth2" de Tailwindadmin: tarjeta centrada sobre fondo tenue de marca. */
export default function AuthLayout() {
    return (
        <div className="relative flex min-h-screen items-center justify-center overflow-hidden bg-lightprimary px-4 py-10">
            <div
                aria-hidden
                className="pointer-events-none absolute -top-32 -right-32 size-96 rounded-full bg-prompt-500/15 blur-3xl"
            />
            <div
                aria-hidden
                className="pointer-events-none absolute -bottom-32 -left-32 size-96 rounded-full bg-brand-500/15 blur-3xl"
            />

            <div className="relative w-full md:w-[450px]">
                <Card className="gap-0 border-none p-8 shadow-lg">
                    <Logo className="mx-auto mb-8" />
                    <Outlet />
                </Card>

                <ul className="mt-6 flex flex-wrap items-center justify-center gap-x-5 gap-y-2 text-xs text-muted-foreground">
                    {FEATURES.map((feature) => (
                        <li key={feature.label} className="flex items-center gap-1.5">
                            <feature.icon className="size-3.5 text-primary dark:text-brand-200" />
                            {feature.label}
                        </li>
                    ))}
                </ul>
            </div>
        </div>
    );
}
