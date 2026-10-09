import { isAuthFailure, notifyAuthExpired, xsrfToken } from '@/lib/session';

async function fail(response: Response): Promise<never> {
    if (isAuthFailure(response)) {
        notifyAuthExpired();

        throw new Error('Your session expired. Sign in to continue.');
    }

    let message = `Request failed (${response.status})`;

    try {
        const json = await response.json();
        message = json.error?.message ?? json.message ?? message;
    } catch {
        // Not JSON; keep the generic message.
    }

    throw new Error(message);
}

const JSON_HEADERS = {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
};

export async function getJson<T>(
    url: string,
    signal?: AbortSignal,
): Promise<T> {
    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: JSON_HEADERS,
        signal,
    });

    if (!response.ok || response.redirected) {
        return fail(response);
    }

    return (await response.json()) as T;
}

async function sendJson<T>(
    method: 'POST' | 'PATCH' | 'DELETE',
    url: string,
    body?: unknown,
): Promise<T> {
    const token = xsrfToken();
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            ...JSON_HEADERS,
            ...(body === undefined
                ? {}
                : { 'Content-Type': 'application/json' }),
            ...(token ? { 'X-XSRF-TOKEN': token } : {}),
        },
        body: body === undefined ? undefined : JSON.stringify(body),
    });

    if (!response.ok || response.redirected) {
        return fail(response);
    }

    return (await response.json()) as T;
}

export const patchJson = <T>(url: string, body: unknown) =>
    sendJson<T>('PATCH', url, body);

export const postJson = <T>(url: string, body?: unknown) =>
    sendJson<T>('POST', url, body);

export const deleteJson = <T>(url: string) => sendJson<T>('DELETE', url);
