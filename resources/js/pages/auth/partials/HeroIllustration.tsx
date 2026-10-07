import { CheckCheck, Mail, MessageCircle, MessagesSquare } from 'lucide-react';
import { useEffect, useRef } from 'react';
import { cn } from '@/lib/utils';

// Tamaño de diseño (1x del boceto). La ilustración se dibuja a este tamaño y
// se escala completa para entrar en el espacio libre del panel (ancho y alto).
const WIDTH = 572;
const HEIGHT = 300;

/**
 * Ilustración del login: tarjeta de mensaje entregado, tarjeta de campaña e
 * ícono flotante unidos por un recorrido punteado. Posiciones medidas sobre el
 * boceto de Figma. Ocupa el espacio sobrante del panel (flex-1).
 */
export default function HeroIllustration({ className }: { className?: string }) {
    const wrapperRef = useRef<HTMLDivElement>(null);

    // --fit = escala que entra en el espacio libre (máx. 1). Se recalcula cuando cambia el panel.
    useEffect(() => {
        const wrapper = wrapperRef.current;
        if (!wrapper) return;

        const observer = new ResizeObserver(([entry]) => {
            const { width, height } = entry.contentRect;
            const fit = Math.min(1, width / WIDTH, height / HEIGHT);
            wrapper.style.setProperty('--fit', fit.toFixed(4));
        });
        observer.observe(wrapper);
        return () => observer.disconnect();
    }, []);

    return (
        <div
            ref={wrapperRef}
            aria-hidden
            className={cn('relative min-h-[140px] w-full flex-1 [--fit:1]', className)}
        >
            <div
                className="absolute top-1/2 left-1/2"
                style={{
                    width: WIDTH,
                    height: HEIGHT,
                    transform: 'translate(-50%, -50%) scale(var(--fit))',
                }}
            >
                {/* Círculo de fondo */}
                <div className="absolute -top-3 left-[150px] size-[310px] rounded-full bg-prompt-100/60 dark:bg-prompt-500/10" />

                {/* Recorrido punteado */}
                <svg
                    viewBox={`0 0 ${WIDTH} ${HEIGHT}`}
                    fill="none"
                    className="absolute inset-0 size-full text-brand-200 dark:text-brand-400/40"
                >
                    <g stroke="currentColor" strokeWidth="1.75" strokeDasharray="5 7" strokeLinecap="round">
                        <path d="M77 50C50 95 36 160 50 205c8 25 22 50 44 57 33 11 72-2 104-24 28-20 58-38 88-36 20 1 34 7 46 14" />
                        <path d="M541 92c6 52 4 108-10 152-10 32-34 50-74 46" />
                    </g>
                </svg>

                {/* Tarjeta: mensaje entregado */}
                <div className="absolute top-[31px] left-[82px] w-[382px] rounded-2xl bg-card p-6 shadow-[0_12px_40px_-12px] shadow-brand-900/15 dark:bg-popover">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <span className="flex size-[30px] items-center justify-center rounded-lg bg-prompt-50 dark:bg-prompt-500/15">
                                <MessageCircle className="size-[18px] text-tenant-accent dark:text-prompt-400" strokeWidth={2} />
                            </span>
                            <span className="text-[13px] font-semibold text-brand-700 dark:text-white">Tu empresa</span>
                        </div>
                        <span className="text-xs text-muted-foreground">Ahora</span>
                    </div>
                    <p className="mt-4 text-2xl text-brand-700 dark:text-white">¡Hola, Ana!</p>
                    <p className="mt-1 text-sm text-muted-foreground">Tenemos novedades para ti.</p>
                    <p className="mt-4 flex items-center gap-1.5 text-xs font-medium text-tenant-accent dark:text-prompt-400">
                        <CheckCheck className="size-4" />
                        Entregado
                    </p>
                </div>

                {/* Ícono flotante */}
                <div className="absolute top-[218px] left-[31px] flex size-[58px] items-center justify-center rounded-2xl bg-card shadow-[0_12px_32px_-10px] shadow-brand-900/20 dark:bg-popover">
                    <MessagesSquare className="size-[26px] text-blue-700 dark:text-blue-300" strokeWidth={1.75} />
                </div>

                {/* Tarjeta: campaña */}
                <div className="absolute top-[213px] left-[245px] flex w-[300px] items-center gap-3.5 rounded-2xl bg-brand-700 px-[17px] py-[18px] shadow-[0_16px_40px_-12px] shadow-brand-900/40">
                    <span className="flex size-[38px] shrink-0 items-center justify-center rounded-xl bg-white/10">
                        <Mail className="size-5 text-white" strokeWidth={1.75} />
                    </span>
                    <div className="min-w-0">
                        <p className="text-[15px] font-semibold text-white">Un mensaje que conecta.</p>
                        <p className="mt-0.5 text-[13px] text-brand-100">Email · Tu próxima campaña</p>
                    </div>
                </div>
            </div>
        </div>
    );
}
