import { useId } from 'react';
import { cn } from '@/lib/utils';

/** Isotipo de AlertPrompt: burbuja de chat con flecha de envío (azul → teal). */
export default function BrandMark({ className }: { className?: string }) {
    const gradientId = useId();

    return (
        <svg viewBox="0 0 64 56" fill="none" aria-hidden className={cn('size-10', className)}>
            <defs>
                <linearGradient id={gradientId} x1="12" y1="6" x2="60" y2="44" gradientUnits="userSpaceOnUse">
                    <stop offset="0" stopColor="#1d4ed8" />
                    <stop offset="1" stopColor="#1fbaa6" />
                </linearGradient>
            </defs>
            {/* Líneas de velocidad */}
            <g stroke="#1d4ed8" strokeWidth="3.5" strokeLinecap="round">
                <path d="M3 20h7" />
                <path d="M1 27h9" />
                <path d="M4 34h6" />
            </g>
            {/* Burbuja con cola */}
            <path
                d="M22 4h26a8 8 0 0 1 8 8v4M56 34v0a8 8 0 0 1-8 8H30l-9 9v-9h0a8 8 0 0 1-7-8V12a8 8 0 0 1 8-8"
                stroke={`url(#${gradientId})`}
                strokeWidth="4.5"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
            {/* Puntos de "escribiendo" */}
            <g fill="#164f79">
                <circle cx="26" cy="21" r="3" />
                <circle cx="35" cy="21" r="3" />
                <circle cx="44" cy="21" r="3" />
            </g>
            {/* Flecha de envío */}
            <g stroke="#1fbaa6" strokeWidth="4.5" strokeLinecap="round" strokeLinejoin="round">
                <path d="M38 31h22" />
                <path d="M53 24l7 7-7 7" />
            </g>
        </svg>
    );
}
