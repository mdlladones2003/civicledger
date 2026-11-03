import { createContext, useContext, useEffect, useState, type ReactNode } from 'react';

type Theme = 'light' | 'dark';

type ThemeContextValue = {
    theme: Theme;
    toggleTheme: () => void;
};

const ThemeContext = createContext<ThemeContextValue | null>(null);

function getInitialTheme(): Theme {
    if (typeof window === 'undefined') {
        return 'light';
    }

    try {
        const savedTheme = localStorage.getItem('cl_theme');

        if (savedTheme === 'light' || savedTheme === 'dark') {
            return savedTheme;
        }
    } catch (error) {
        console.warn('Unable to read the saved theme preference.', error);
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

export function ThemeProvider({ children }: { children: ReactNode }) {
    const [theme, setTheme] = useState<Theme>(getInitialTheme);

    useEffect(() => {
        document.documentElement.classList.toggle('dark', theme === 'dark');

        try {
            localStorage.setItem('cl_theme', theme);
        } catch (error) {
            console.warn('Unable to save the theme preference.', error);
        }
    }, [theme]);

    function toggleTheme() {
        setTheme((currentTheme) => currentTheme === 'dark' ? 'light' : 'dark');
    }

    return (
        <ThemeContext.Provider value={{ theme, toggleTheme }}>
            {children}
        </ThemeContext.Provider>
    );
}

export function useTheme(): ThemeContextValue {
    const context = useContext(ThemeContext);

    if (!context) {
        throw new Error('useTheme must be used within a ThemeProvider.');
    }

    return context;
}
