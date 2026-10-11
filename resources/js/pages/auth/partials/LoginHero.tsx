import { Mail, MessageCircle, Smartphone } from 'lucide-react';
import HeroIllustration from './HeroIllustration';
import SkylineArt from './SkylineArt';

const CHANNELS = [
    { label: 'WhatsApp', icon: MessageCircle },
    { label: 'SMS', icon: Smartphone },
    { label: 'Email', icon: Mail },
];

/**
 * Panel izquierdo del login: propuesta de valor + ilustración de mensajes.
 * Con marca de empresa, `title` reemplaza el titular de AlertPrompt.
 */
export default function LoginHero({ title }: { title?: string | null }) {
    return (
        <section className="relative flex h-full flex-col overflow-hidden rounded-3xl bg-gradient-to-b from-slate-50 via-slate-50 to-prompt-50 px-12 pt-14 short:pt-9 shorter:pt-6 dark:from-white/[0.05] dark:via-white/[0.04] dark:to-prompt-500/10">
            <h1 className="text-[2.75rem] leading-[1.1] font-bold tracking-tight text-brand-700 short:text-[2.25rem] shorter:text-[1.9rem] dark:text-white">
                {title ? (
                    <span className="block max-w-lg [overflow-wrap:anywhere]">{title}</span>
                ) : (
                    <>
                        Comunicaciones
                        <br />
                        Masivas <span className="text-tenant-accent dark:text-prompt-400">Sencillas.</span>
                    </>
                )}
            </h1>
            <p className="mt-5 max-w-md text-base leading-relaxed text-muted-foreground short:mt-3 shorter:mt-2 shorter:text-sm">
                Conecta con tus clientes por WhatsApp, SMS y Email desde un solo lugar.
            </p>

            <ul className="mt-6 flex flex-wrap gap-2.5 short:mt-4 shorter:mt-3">
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

            {/* Ocupa el espacio libre y se escala para entrar en cualquier alto de pantalla. */}
            <HeroIllustration className="mt-6 short:mt-3 shorter:mt-2" />

            <p className="relative z-10 mt-3 text-center text-sm text-muted-foreground shorter:mt-1">
                Todos tus canales. Un mismo lugar.
            </p>

            <SkylineArt className="-mx-12 mt-4 h-28 shrink-0 short:h-20 shorter:mt-2 shorter:h-14 w-[calc(100%+6rem)] max-w-none text-brand-200/70 dark:text-brand-400/30" />
        </section>
    );
}
