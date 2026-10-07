import { Link } from 'react-router-dom';
import { cn } from '@/lib/utils';

export default function Logo({ className }: { className?: string }) {
    return (
        <Link to="/" className={cn('flex items-center gap-2.5', className)}>
            <span className="flex size-9 items-center justify-center rounded-lg bg-gradient-to-br from-brand-600 to-prompt-600 text-base font-bold text-white shadow-sm">
                A
            </span>
            <span className="text-lg font-bold tracking-tight text-foreground">
                Alert<span className="text-prompt-600 dark:text-prompt-400">Prompt</span>
            </span>
        </Link>
    );
}
