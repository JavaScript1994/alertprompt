import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { createRoot } from 'react-dom/client';
import Root from '@/Root';
import '../css/app.css';

const queryClient = new QueryClient();

const container = document.getElementById('app');

if (!container) {
    throw new Error('#app element not found');
}

createRoot(container).render(
    <QueryClientProvider client={queryClient}>
        <Root />
    </QueryClientProvider>,
);
