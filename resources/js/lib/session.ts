// Session expiry is announced with window events so that anything that talks to
// the server (the scan outbox, fetch helpers) can raise it, and one dialog in
// the layout can answer it, without those pieces knowing about each other.
const EXPIRED = 'freight:auth-expired';
const RESTORED = 'freight:auth-restored';

export function notifyAuthExpired(): void {
    window.dispatchEvent(new Event(EXPIRED));
}

export function notifyAuthRestored(): void {
    window.dispatchEvent(new Event(RESTORED));
}

function listen(name: string, callback: () => void): () => void {
    window.addEventListener(name, callback);

    return () => window.removeEventListener(name, callback);
}

export const onAuthExpired = (callback: () => void) =>
    listen(EXPIRED, callback);
export const onAuthRestored = (callback: () => void) =>
    listen(RESTORED, callback);

/** The status codes a server uses when the session is gone. */
export function isAuthFailure(response: Response): boolean {
    return (
        response.status === 401 ||
        response.status === 419 ||
        // An API call that got bounced to a login page.
        response.redirected
    );
}

export function xsrfToken(): string | undefined {
    const raw = document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];

    return raw ? decodeURIComponent(raw) : undefined;
}

/**
 * Sign the same user back in with their PIN, in place. Resolves to an error
 * message when it did not work, or null on success.
 */
export async function reauthenticate(
    username: string,
    pin: string,
): Promise<string | null> {
    try {
        // Make sure a fresh session and CSRF cookie exist first.
        await fetch('/session/ping', { credentials: 'same-origin' });

        const token = xsrfToken();
        const response = await fetch('/login/pin', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(token ? { 'X-XSRF-TOKEN': token } : {}),
            },
            body: JSON.stringify({ username, pin }),
        });

        // Redirected means we are already signed in again (another tab).
        if (response.ok || response.redirected) {
            return null;
        }

        const json = await response.json().catch(() => ({}));

        return (
            json.errors?.pin?.[0] ??
            json.errors?.username?.[0] ??
            json.message ??
            'Could not sign in. Try again.'
        );
    } catch {
        return 'No connection. Try again when you are back online.';
    }
}
