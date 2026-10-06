import { MessageSquareText, ShieldCheck, Zap } from 'lucide-react';
import { Outlet } from 'react-router-dom';

const FEATURES = [
    {
        icon: MessageSquareText,
        title: 'WhatsApp, SMS y Email',
        description: 'Un solo lugar para todos tus canales de mensajería masiva.',
    },
    {
        icon: ShieldCheck,
        title: 'Cumplimiento Ley N° 32323',
        description: 'Consentimiento y supresión automática, siempre trazables.',
    },
    {
        icon: Zap,
        title: 'Entregas en vivo',
        description: 'Seguí el estado de cada campaña en tiempo real.',
    },
];

export default function AuthLayout() {
    return (
        <div className="flex min-h-screen bg-surface">
            <div className="relative hidden w-[44%] flex-col justify-between overflow-hidden bg-gradient-to-br from-brand-700 via-brand-900 to-prompt-700 px-12 py-12 lg:flex">
                <div
                    className="pointer-events-none absolute inset-0 opacity-[0.06]"
                    style={{
                        backgroundImage:
                            'linear-gradient(#fff 1px, transparent 1px), linear-gradient(90deg, #fff 1px, transparent 1px)',
                        backgroundSize: '40px 40px',
                    }}
                />
                <div
                    className="pointer-events-none absolute top-0 -right-24 h-96 w-96 rounded-full bg-white/10 blur-[110px]"
                    aria-hidden
                />
                <div
                    className="pointer-events-none absolute -bottom-24 -left-16 h-80 w-80 rounded-full bg-white/10 blur-[110px]"
                    aria-hidden
                />

                <div className="relative flex items-center gap-2.5">
                    <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-white text-sm font-bold text-brand-700">
                        A
                    </div>
                    <span className="text-sm font-semibold text-white">AlertPrompt</span>
                </div>

                <div className="relative">
                    <h1 className="max-w-md text-3xl leading-tight font-semibold text-white">
                        Comunicaciones masivas, sin dolores de cabeza.
                    </h1>
                    <p className="mt-3 max-w-sm text-sm text-brand-100">
                        La plataforma para que empresas peruanas envíen campañas por WhatsApp, SMS y Email — con
                        consentimiento, trazabilidad y control de calidad de fábrica.
                    </p>

                    <div className="mt-10 space-y-5">
                        {FEATURES.map((feature) => (
                            <div key={feature.title} className="flex items-start gap-3">
                                <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-white/20 bg-white/10">
                                    <feature.icon className="h-4 w-4 text-white" strokeWidth={1.75} />
                                </div>
                                <div>
                                    <p className="text-sm font-medium text-white">{feature.title}</p>
                                    <p className="text-sm text-brand-100">{feature.description}</p>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                <p className="relative text-xs text-brand-200">© {new Date().getFullYear()} AlertPrompt</p>
            </div>

            <div className="flex flex-1 items-center justify-center px-6 py-12">
                <div className="w-full max-w-sm">
                    <div className="mb-8 text-center lg:hidden">
                        <div className="mx-auto mb-3 flex h-10 w-10 items-center justify-center rounded-xl bg-brand-600 text-base font-bold text-white shadow-sm">
                            A
                        </div>
                        <h1 className="text-lg font-semibold text-ink-900">AlertPrompt</h1>
                    </div>

                    <div className="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
                        <Outlet />
                    </div>
                </div>
            </div>
        </div>
    );
}
