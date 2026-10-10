import { QueryClientProvider } from '@tanstack/react-query';
import { createRoot } from 'react-dom/client';
import { ThemeProvider } from '@/components/providers/ThemeProvider';
import { TooltipProvider } from '@/components/ui/tooltip';
import { installMfaInterceptors } from '@/features/mfa/interceptors';
import ReauthModal from '@/features/mfa/ReauthModal';
import { queryClient } from '@/lib/queryClient';
import Root from '@/Root';
import '../css/app.css';

installMfaInterceptors();

const container = document.getElementById('app');

if (!container) {
    throw new Error('#app element not found');
}

createRoot(container).render(
    <QueryClientProvider client={queryClient}>
        <ThemeProvider>
            <TooltipProvider delayDuration={200}>
                <Root />
                <ReauthModal />
            </TooltipProvider>
        </ThemeProvider>
    </QueryClientProvider>,
);
