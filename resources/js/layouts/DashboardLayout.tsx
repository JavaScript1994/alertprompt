import { VisuallyHidden } from '@radix-ui/react-visually-hidden';
import { useState } from 'react';
import { Outlet } from 'react-router-dom';
import { Sheet, SheetContent, SheetDescription, SheetTitle } from '@/components/ui/sheet';
import Header from './partials/Header';
import ImpersonationBanner from './partials/ImpersonationBanner';
import Sidebar from './partials/Sidebar';

const CURRENT_YEAR = new Date().getFullYear();

/** FullLayout de Tailwindadmin: sidebar fijo de 270px en desktop, Sheet en móvil. */
export default function DashboardLayout() {
    const [isSidebarOpen, setIsSidebarOpen] = useState(false);

    return (
        <div className="min-h-screen">
            <aside className="fixed inset-y-0 left-0 z-40 hidden w-[270px] border-r bg-sidebar lg:block">
                <Sidebar />
            </aside>

            <Sheet open={isSidebarOpen} onOpenChange={setIsSidebarOpen}>
                <SheetContent side="left" className="w-[270px] p-0">
                    <VisuallyHidden>
                        <SheetTitle>Menú de navegación</SheetTitle>
                        <SheetDescription>Secciones de AlertPrompt</SheetDescription>
                    </VisuallyHidden>
                    {/* Cierra el menú móvil al elegir una sección. */}
                    <Sidebar onNavigate={() => setIsSidebarOpen(false)} />
                </SheetContent>
            </Sheet>

            <div className="flex min-h-screen flex-col lg:pl-[270px]">
                <ImpersonationBanner />
                <Header onOpenSidebar={() => setIsSidebarOpen(true)} />

                <main className="flex-1 px-4 pt-4 pb-10 sm:px-6 lg:pt-6">
                    <div className="mx-auto max-w-7xl">
                        <Outlet />
                    </div>
                </main>

                <footer className="px-6 pb-6 text-center text-xs text-muted-foreground">
                    © {CURRENT_YEAR} AlertPrompt · Mensajería con consentimiento (Ley N° 32323)
                </footer>
            </div>
        </div>
    );
}
