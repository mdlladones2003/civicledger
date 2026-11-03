import React from 'react';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import AppPage from './Pages/AppPage';
import { ThemeProvider } from './components/app/ThemeProvider';

createInertiaApp({
    title: (title) => (title ? `${title} | CivicLedger` : 'CivicLedger'),
    resolve: async () => AppPage,
    setup({ el, App, props }) {
        createRoot(el).render(
            <ThemeProvider>
                <App {...props} />
            </ThemeProvider>,
        );
    },
});
