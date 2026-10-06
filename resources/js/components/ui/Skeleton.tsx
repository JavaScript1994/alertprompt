export function Skeleton({ className = '' }: { className?: string }) {
    return <div className={`animate-pulse rounded bg-slate-200/70 ${className}`} />;
}

export function SkeletonCard() {
    return (
        <div className="space-y-4 rounded-xl border border-slate-200 bg-white p-6">
            <Skeleton className="h-5 w-1/3" />
            <Skeleton className="h-4 w-2/3" />
            <Skeleton className="h-4 w-1/2" />
        </div>
    );
}

export function SkeletonTable({ rows = 5 }: { rows?: number }) {
    return (
        <div className="space-y-3">
            {Array.from({ length: rows }).map((_, i) => (
                <div key={i} className="flex items-center gap-4">
                    <div className="flex-1 space-y-2">
                        <Skeleton className="h-4 w-1/3" />
                        <Skeleton className="h-3 w-1/4" />
                    </div>
                    <Skeleton className="h-6 w-16 rounded-full" />
                </div>
            ))}
        </div>
    );
}
