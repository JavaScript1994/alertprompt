import { Link } from 'react-router-dom';
import { cn } from '@/lib/utils';
import BrandMark from './BrandMark';

export default function Logo({ className, size = 'md' }: { className?: string; size?: 'md' | 'lg' }) {
    return (
        <Link to="/" className={cn('flex items-center gap-2', className)}>
            <BrandMark className={size === 'lg' ? 'size-12' : 'size-10'} />
            <span
                className={cn(
                    'font-bold tracking-tight text-brand-700 dark:text-white',
                    size === 'lg' ? 'text-[1.75rem]' : 'text-xl',
                )}
            >
                Alert<span className="text-prompt-600 dark:text-prompt-400">Prompt</span>
            </span>
        </Link>
    );
}
