import { useEffect, useMemo, useRef, useState } from 'react';
import { isoDate } from '@/lib/format';
import type { DeliveryCounts } from '@/types';

type Day = DeliveryCounts & { day: string };

const HEIGHT = 200;
const PAD = { top: 12, right: 8, bottom: 24, left: 40 };

function formatDay(iso: string): string {
    const [, month, day] = iso.split('-');
    return `${Number(day)}/${Number(month)}`;
}

/** Rellena con ceros los días sin envíos para que el eje de tiempo sea continuo. */
function fillDays(days: Day[], from: string, to: string): Day[] {
    const byDay = new Map(days.map((d) => [d.day, d]));
    const start = new Date(`${from}T00:00:00`);
    const total = Math.round((new Date(`${to}T00:00:00`).getTime() - start.getTime()) / 86_400_000) + 1;

    return Array.from({ length: Math.max(total, 0) }, (_, offset) => {
        const date = new Date(start);
        date.setDate(start.getDate() + offset);
        const iso = isoDate(date);

        return byDay.get(iso) ?? { day: iso, attempted: 0, sent: 0, delivered: 0, read: 0, failed: 0, skipped: 0, delivery_rate: 0 };
    });
}

/** Máximo "redondo" y divisible entre 2, para que la marca del medio sea exacta. */
function niceMax(value: number): number {
    if (value <= 4) return 4;
    const magnitude = 10 ** Math.floor(Math.log10(value));
    const max = Math.ceil(value / magnitude) * magnitude;
    return (max / magnitude) % 2 === 0 ? max : max + magnitude;
}

/**
 * Barras apiladas por día: entregados (verde) y fallidos (rojo) — son
 * estados, así que usan los tokens de estado con leyenda y tooltip.
 */
export default function DailyDeliveryChart({ days, from, to }: { days: Day[]; from: string; to: string }) {
    const svgRef = useRef<SVGSVGElement>(null);
    const [width, setWidth] = useState(720);

    useEffect(() => {
        const node = svgRef.current;
        if (!node) return;
        const observer = new ResizeObserver(([entry]) => setWidth(Math.max(320, Math.round(entry.contentRect.width))));
        observer.observe(node);
        return () => observer.disconnect();
    }, []);
    const [hovered, setHovered] = useState<number | null>(null);
    const series = useMemo(() => fillDays(days, from, to), [days, from, to]);

    const max = niceMax(Math.max(...series.map((d) => d.delivered + d.failed), 0));
    const plotW = width - PAD.left - PAD.right;
    const plotH = HEIGHT - PAD.top - PAD.bottom;
    const slot = plotW / Math.max(series.length, 1);
    const barW = Math.max(2, Math.min(18, slot - 4));
    const y = (value: number) => (value / max) * plotH;
    const ticks = [0, max / 2, max];
    const labelEvery = Math.ceil(series.length / 8);
    const active = hovered !== null ? series[hovered] : null;

    return (
        <div className="relative">
            <div className="mb-3 flex flex-wrap items-center gap-4 text-xs text-muted-foreground">
                <span className="inline-flex items-center gap-1.5">
                    <span className="size-2.5 rounded-sm bg-success" /> Entregados
                </span>
                <span className="inline-flex items-center gap-1.5">
                    <span className="size-2.5 rounded-sm bg-error" /> Fallidos
                </span>
            </div>

            <svg
                ref={svgRef}
                className="h-[200px] w-full"
                viewBox={`0 0 ${width} ${HEIGHT}`}
                aria-labelledby="daily-delivery-title"
                onMouseLeave={() => setHovered(null)}
            >
                <title id="daily-delivery-title">Mensajes entregados y fallidos por día</title>
                {ticks.map((tick) => (
                    <g key={tick}>
                        <line
                            x1={PAD.left}
                            x2={width - PAD.right}
                            y1={PAD.top + plotH - y(tick)}
                            y2={PAD.top + plotH - y(tick)}
                            className="stroke-border"
                            strokeDasharray={tick === 0 ? undefined : '3 3'}
                        />
                        <text x={PAD.left - 8} y={PAD.top + plotH - y(tick) + 4} textAnchor="end" className="fill-muted-foreground text-[10px]">
                            {Math.round(tick).toLocaleString('es-PE')}
                        </text>
                    </g>
                ))}

                {series.map((day, index) => {
                    const x = PAD.left + index * slot + (slot - barW) / 2;
                    const deliveredH = y(day.delivered);
                    const failedH = y(day.failed);
                    // 2px de separación entre segmentos apilados.
                    const gap = deliveredH > 0 && failedH > 0 ? 2 : 0;

                    return (
                        <g key={day.day} onMouseEnter={() => setHovered(index)}>
                            {/* Zona de hover más grande que la barra. */}
                            <rect x={PAD.left + index * slot} y={PAD.top} width={slot} height={plotH} className="fill-transparent" />
                            {hovered === index && (
                                <rect x={PAD.left + index * slot} y={PAD.top} width={slot} height={plotH} className="fill-muted/60" />
                            )}
                            {deliveredH > 0 && (
                                <rect x={x} y={PAD.top + plotH - deliveredH} width={barW} height={deliveredH} rx={Math.min(4, barW / 2)} className="fill-success" />
                            )}
                            {failedH > 0 && (
                                <rect
                                    x={x}
                                    y={PAD.top + plotH - deliveredH - gap - failedH}
                                    width={barW}
                                    height={failedH}
                                    rx={Math.min(4, barW / 2)}
                                    className="fill-error"
                                />
                            )}
                            {index % labelEvery === 0 && (
                                <text x={x + barW / 2} y={HEIGHT - 6} textAnchor="middle" className="fill-muted-foreground text-[10px]">
                                    {formatDay(day.day)}
                                </text>
                            )}
                        </g>
                    );
                })}
            </svg>

            {active && hovered !== null && (
                <div
                    className="pointer-events-none absolute top-8 z-10 rounded-lg border bg-popover px-3 py-2 text-xs shadow-md"
                    style={{
                        left: Math.min(PAD.left + hovered * slot + slot / 2, width - 150),
                    }}
                >
                    <p className="font-semibold text-foreground">{formatDay(active.day)}</p>
                    <p className="mt-1 flex items-center gap-1.5 text-muted-foreground">
                        <span className="size-2 rounded-sm bg-success" /> Entregados: <span className="text-foreground tabular-nums">{active.delivered.toLocaleString('es-PE')}</span>
                    </p>
                    <p className="flex items-center gap-1.5 text-muted-foreground">
                        <span className="size-2 rounded-sm bg-error" /> Fallidos: <span className="text-foreground tabular-nums">{active.failed.toLocaleString('es-PE')}</span>
                    </p>
                    <p className="text-muted-foreground">
                        Intentos: <span className="text-foreground tabular-nums">{active.attempted.toLocaleString('es-PE')}</span>
                    </p>
                </div>
            )}
        </div>
    );
}
