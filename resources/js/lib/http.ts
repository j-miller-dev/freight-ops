function xsrfToken(): string | undefined {
    const raw = document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];

    return raw ? decodeURIComponent(raw) : undefined;
}

async function fail(response: Response): Promise<never> {
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

    if (!response.ok) {
        return fail(response);
    }

    return (await response.json()) as T;
}

export async function patchJson<T>(url: string, body: unknown): Promise<T> {
    const token = xsrfToken();
    const response = await fetch(url, {
        method: 'PATCH',
        credentials: 'same-origin',
        headers: {
            ...JSON_HEADERS,
            'Content-Type': 'application/json',
            ...(token ? { 'X-XSRF-TOKEN': token } : {}),
        },
        body: JSON.stringify(body),
    });

    if (!response.ok) {
        return fail(response);
    }

    return (await response.json()) as T;
}
