import { createContext, useContext, useEffect, useState, type ReactNode } from 'react';

export type Theme = 'light' | 'dark' | 'system';

const STORAGE_KEY = 'alertprompt-theme';

interface ThemeContextValue {
    theme: Theme;
    /** Tema efectivo tras resolver "system" contra la preferencia del SO. */
    resolvedTheme: 'light' | 'dark';
    setTheme: (theme: Theme) => void;
}

const ThemeContext = createContext<ThemeContextValue | null>(null);

// localStorage puede no estar disponible (modo privado, bloqueado por el
// navegador): el tema es una preferencia, nunca debe romper la app.
function readStoredTheme(): Theme {
    try {
        const value = window.localStorage.getItem(STORAGE_KEY);
        return value === 'light' || value === 'dark' || value === 'system' ? value : 'system';
    } catch {
        return 'system';
    }
}

function systemPrefersDark(): boolean {
    return window.matchMedia('(prefers-color-scheme: dark)').matches;
}

export function ThemeProvider({ children }: { children: ReactNode }) {
    const [theme, setThemeState] = useState<Theme>(readStoredTheme);
    const [prefersDark, setPrefersDark] = useState(systemPrefersDark);

    useEffect(() => {
        const media = window.matchMedia('(prefers-color-scheme: dark)');
        const onChange = (event: MediaQueryListEvent) => setPrefersDark(event.matches);
        media.addEventListener('change', onChange);
        return () => media.removeEventListener('change', onChange);
    }, []);

    const resolvedTheme = theme === 'system' ? (prefersDark ? 'dark' : 'light') : theme;

    useEffect(() => {
        document.documentElement.classList.toggle('dark', resolvedTheme === 'dark');
        document.documentElement.style.colorScheme = resolvedTheme;
    }, [resolvedTheme]);

    const setTheme = (next: Theme) => {
        try {
            window.localStorage.setItem(STORAGE_KEY, next);
        } catch {
            // Sin persistencia: el cambio aplica solo a esta sesión.
        }
        setThemeState(next);
    };

    return <ThemeContext.Provider value={{ theme, resolvedTheme, setTheme }}>{children}</ThemeContext.Provider>;
}

export function useTheme(): ThemeContextValue {
    const context = useContext(ThemeContext);
    if (!context) {
        throw new Error('useTheme debe usarse dentro de <ThemeProvider>');
    }
    return context;
}
