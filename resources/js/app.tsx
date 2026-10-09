import { createInertiaApp, router } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AuthLayout from '@/layouts/auth-layout';
import KioskLayout from '@/layouts/kiosk-layout';
import SettingsLayout from '@/layouts/settings/layout';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name.startsWith('auth/'):
                return AuthLayout;
            case name === 'dashboard' || name.startsWith('loading/'):
                return KioskLayout;
            case name.startsWith('settings/'):
                return [KioskLayout, SettingsLayout];
            default:
                return KioskLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// An Inertia request that comes back 419 means the CSRF token went stale with
// the session. Reloading fetches a fresh one (and the login page if needed)
// instead of leaving an error overlay on screen.
router.on('httpException', (event) => {
    if (event.detail.response.status === 419) {
        window.location.reload();

        return false;
    }
});

// This will set light / dark mode on load...
initializeTheme();
