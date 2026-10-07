import { CheckCheck, Mail, MessageCircle, MessagesSquare, Smartphone } from 'lucide-react';
import SkylineArt from './SkylineArt';

const CHANNELS = [
    { label: 'WhatsApp', icon: MessageCircle },
    { label: 'SMS', icon: Smartphone },
    { label: 'Email', icon: Mail },
];

/** Panel izquierdo del login: propuesta de valor + ilustración de mensajes. */
export default function LoginHero() {
    return (
        <section className="relative flex h-full flex-col overflow-hidden rounded-3xl bg-gradient-to-b from-slate-50 via-slate-50 to-prompt-50 px-12 pt-14 dark:from-white/[0.05] dark:via-white/[0.04] dark:to-prompt-500/10">
            <h1 className="text-[2.75rem] leading-[1.1] font-bold tracking-tight text-brand-700 dark:text-white">
                Comunicaciones
                <br />
                Masivas <span className="text-tenant-accent dark:text-prompt-400">Sencillas.</span>
            </h1>
            <p className="mt-5 max-w-md text-base leading-relaxed text-muted-foreground">
                Conecta con tus clientes por WhatsApp, SMS y Email desde un solo lugar.
            </p>

            <ul className="mt-6 flex flex-wrap gap-2.5">
                {CHANNELS.map((channel) => (
                    <li
                        key={channel.label}
                        className="flex items-center gap-2 rounded-full border bg-card px-3.5 py-1.5 text-sm font-semibold text-brand-700 dark:text-white"
                    >
                        <channel.icon className="size-4 text-tenant-accent dark:text-prompt-400" strokeWidth={2} />
                        {channel.label}
                    </li>
                ))}
            </ul>

            {/* Ilustración: tarjetas de mensaje sobre un recorrido punteado */}
            <div className="relative mx-auto mt-12 h-[270px] w-full max-w-[480px]" aria-hidden>
                <div className="absolute top-0 left-1/2 size-64 -translate-x-1/2 rounded-full bg-prompt-100/50 dark:bg-prompt-500/10" />
                <svg viewBox="0 0 480 270" fill="none" className="absolute inset-0 size-full">
                    <path
                        d="M40 40C0 120 10 200 70 230c60 30 120-30 170-50M440 60c20 60 10 120-20 170"
                        stroke="currentColor"
                        strokeWidth="1.5"
                        strokeDasharray="4 6"
                        className="text-prompt-300 dark:text-prompt-700"
                    />
                </svg>

                <div className="absolute top-2 left-[8%] w-[76%] rounded-2xl bg-card p-5 shadow-lg shadow-brand-900/5 dark:bg-popover">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2.5">
                            <span className="flex size-8 items-center justify-center rounded-full bg-prompt-50 dark:bg-prompt-500/15">
                                <MessageCircle className="size-4 text-tenant-accent dark:text-prompt-400" />
                            </span>
                            <span className="text-sm font-semibold text-foreground">Tu empresa</span>
                        </div>
                        <span className="text-xs text-muted-foreground">Ahora</span>
                    </div>
                    <p className="mt-4 text-xl text-foreground">¡Hola, Ana!</p>
                    <p className="mt-1 text-sm text-muted-foreground">Tenemos novedades para ti.</p>
                    <p className="mt-4 flex items-center gap-1.5 text-xs font-medium text-tenant-accent dark:text-prompt-400">
                        <CheckCheck className="size-3.5" />
                        Entregado
                    </p>
                </div>

                <div className="absolute top-[170px] -left-1 flex size-14 items-center justify-center rounded-2xl bg-card shadow-lg shadow-brand-900/5 dark:bg-popover">
                    <MessagesSquare className="size-6 text-brand-600 dark:text-brand-200" strokeWidth={1.75} />
                </div>

                <div className="absolute top-[178px] right-0 flex w-[60%] items-center gap-3 rounded-2xl bg-brand-700 px-4 py-3.5 shadow-xl shadow-brand-900/20">
                    <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-white/10">
                        <Mail className="size-4.5 text-white" />
                    </span>
                    <div className="min-w-0">
                        <p className="truncate text-sm font-semibold text-white">Un mensaje que conecta.</p>
                        <p className="truncate text-xs text-brand-100">Email · Tu próxima campaña</p>
                    </div>
                </div>
            </div>

            <p className="relative z-10 text-center text-sm text-muted-foreground">Todos tus canales. Un mismo lugar.</p>

            <SkylineArt className="-mx-12 mt-auto h-28 w-[calc(100%+6rem)] max-w-none text-brand-200/70 dark:text-brand-400/30" />
        </section>
    );
}
