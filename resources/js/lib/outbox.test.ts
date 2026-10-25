import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { db, flushOutbox, MAX_RETRIES } from '@/lib/outbox';
import type { FlushCallbacks, OutboxEntry } from '@/lib/outbox';

function makeEntry(
    overrides: Partial<OutboxEntry> = {},
): Omit<OutboxEntry, 'id'> {
    return {
        client_event_id: '00000000-0000-4000-8000-000000000001',
        manifest_id: 'manifest-uuid',
        barcode: 'BARCODE-001',
        occurred_at: '2026-10-09T08:00:00.000Z',
        acknowledged_warnings: [],
        status: 'pending',
        retry_count: 0,
        created_at: Date.now(),
        ...overrides,
    };
}

function makeCallbacks(
    overrides: Partial<FlushCallbacks> = {},
): FlushCallbacks {
    return {
        onSynced: vi.fn(),
        onWarning: vi.fn(),
        onError: vi.fn(),
        ...overrides,
    };
}

function mockFetch(status: number, body: unknown): void {
    vi.stubGlobal(
        'fetch',
        vi.fn().mockResolvedValue(
            new Response(JSON.stringify(body), {
                status,
                headers: { 'Content-Type': 'application/json' },
            }),
        ),
    );
}

beforeEach(() => {
    vi.unstubAllGlobals();
});

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('outbox database', () => {
    it('persists an entry with pending status', async () => {
        const id = await db.outbox.add(makeEntry());
        const stored = await db.outbox.get(id);

        expect(stored?.status).toBe('pending');
        expect(stored?.barcode).toBe('BARCODE-001');
        expect(stored?.retry_count).toBe(0);
    });
});

describe('flushOutbox', () => {
    it('marks entry synced and calls onSynced on 201', async () => {
        await db.outbox.add(makeEntry());
        mockFetch(201, { data: { connote_number: 'CN-001' } });

        const callbacks = makeCallbacks();
        await flushOutbox(callbacks);

        const entries = await db.outbox.toArray();
        expect(entries[0].status).toBe('synced');
        expect(callbacks.onSynced).toHaveBeenCalledOnce();
        expect(callbacks.onWarning).not.toHaveBeenCalled();
    });

    it('marks entry synced and calls onWarning on 409', async () => {
        await db.outbox.add(makeEntry());
        mockFetch(409, {
            error: {
                code: 'destination_mismatch',
                message: 'Pallet destination does not match manifest.',
                details: {
                    destination_code: 'MEL',
                    manifest_number: 'MAN-001',
                },
            },
        });

        const callbacks = makeCallbacks();
        await flushOutbox(callbacks);

        const entries = await db.outbox.toArray();
        expect(entries[0].status).toBe('synced');
        expect(callbacks.onWarning).toHaveBeenCalledWith(
            expect.objectContaining({ barcode: 'BARCODE-001' }),
            'destination_mismatch',
            expect.objectContaining({
                error: expect.objectContaining({
                    code: 'destination_mismatch',
                }),
            }),
        );
        expect(callbacks.onSynced).not.toHaveBeenCalled();
    });

    it('keeps an entry pending without spending a retry on a network error', async () => {
        await db.outbox.add(makeEntry());
        vi.stubGlobal(
            'fetch',
            vi.fn().mockRejectedValue(new TypeError('Failed to fetch')),
        );

        const callbacks = makeCallbacks();
        await flushOutbox(callbacks);

        const entries = await db.outbox.toArray();
        expect(entries[0].status).toBe('pending');
        expect(entries[0].retry_count).toBe(0);
        expect(entries[0].queued).toBe(true);
        expect(callbacks.onError).toHaveBeenCalledWith(
            expect.anything(),
            'Network error',
        );
    });

    it('never gives up on an entry because the connection is down', async () => {
        await db.outbox.add(makeEntry());
        vi.stubGlobal(
            'fetch',
            vi.fn().mockRejectedValue(new TypeError('Failed to fetch')),
        );

        for (let attempt = 0; attempt < MAX_RETRIES + 3; attempt++) {
            await flushOutbox(makeCallbacks());
        }

        const entries = await db.outbox.toArray();
        expect(entries[0].status).toBe('pending');
        expect(entries[0].retry_count).toBe(0);
    });

    it('stops at the first network error so the rest wait their turn', async () => {
        await db.outbox.add(makeEntry({ client_event_id: 'a', created_at: 1 }));
        await db.outbox.add(makeEntry({ client_event_id: 'b', created_at: 2 }));
        const fetchMock = vi
            .fn()
            .mockRejectedValue(new TypeError('Failed to fetch'));
        vi.stubGlobal('fetch', fetchMock);

        await flushOutbox(makeCallbacks());

        expect(fetchMock).toHaveBeenCalledTimes(1);
        const entries = await db.outbox.orderBy('created_at').toArray();
        expect(entries.map((e) => e.status)).toEqual(['pending', 'pending']);
    });

    it('increments retry_count on a server error and fails after MAX_RETRIES', async () => {
        await db.outbox.add(makeEntry({ retry_count: MAX_RETRIES - 1 }));
        mockFetch(500, { error: { message: 'Server error' } });

        const callbacks = makeCallbacks();
        await flushOutbox(callbacks);

        const entries = await db.outbox.toArray();
        expect(entries[0].status).toBe('failed');
        expect(entries[0].retry_count).toBe(MAX_RETRIES);
        expect(callbacks.onError).toHaveBeenCalledWith(
            expect.anything(),
            'Server error',
        );
    });

    it('preserves occurred_at through the outbox without mutation', async () => {
        const occurred_at = '2026-10-09T08:00:00.000Z';
        await db.outbox.add(makeEntry({ occurred_at }));

        let capturedBody: Record<string, unknown> | null = null;
        vi.stubGlobal(
            'fetch',
            vi.fn().mockImplementation((_url: string, init: RequestInit) => {
                capturedBody = JSON.parse(init.body as string) as Record<
                    string,
                    unknown
                >;

                return Promise.resolve(
                    new Response(JSON.stringify({ data: {} }), {
                        status: 201,
                        headers: { 'Content-Type': 'application/json' },
                    }),
                );
            }),
        );

        await flushOutbox(makeCallbacks());

        expect(
            (capturedBody as Record<string, unknown> | null)?.occurred_at,
        ).toBe(occurred_at);
    });

    it('skips entries that are already synced or failed', async () => {
        await db.outbox.add(makeEntry({ status: 'synced' }));
        await db.outbox.add(makeEntry({ status: 'failed' }));
        const fetchMock = vi.fn();
        vi.stubGlobal('fetch', fetchMock);

        await flushOutbox(makeCallbacks());

        expect(fetchMock).not.toHaveBeenCalled();
    });
});

describe('flushOutbox when the session has expired', () => {
    it.each([401, 419])(
        'keeps the scan pending, spends no retry, and announces it on %i',
        async (status) => {
            await db.outbox.add(makeEntry());
            mockFetch(status, { message: 'Unauthenticated.' });
            const expired = vi.fn();
            window.addEventListener('freight:auth-expired', expired);

            const callbacks = makeCallbacks();
            await flushOutbox(callbacks);

            window.removeEventListener('freight:auth-expired', expired);

            const [entry] = await db.outbox.toArray();
            expect(entry.status).toBe('pending');
            expect(entry.retry_count).toBe(0);
            expect(expired).toHaveBeenCalledOnce();
            expect(callbacks.onError).not.toHaveBeenCalled();
        },
    );

    it('stops at the first expired scan so the rest are not burned', async () => {
        await db.outbox.add(makeEntry({ client_event_id: 'a', created_at: 1 }));
        await db.outbox.add(makeEntry({ client_event_id: 'b', created_at: 2 }));
        mockFetch(401, { message: 'Unauthenticated.' });

        await flushOutbox(makeCallbacks());

        expect(fetch).toHaveBeenCalledOnce();
        expect(
            (await db.outbox.toArray()).map((e) => [e.status, e.retry_count]),
        ).toEqual([
            ['pending', 0],
            ['pending', 0],
        ]);
    });

    it('delivers the scan once the session is back', async () => {
        await db.outbox.add(makeEntry());
        mockFetch(401, {});
        await flushOutbox(makeCallbacks());

        mockFetch(201, { data: { connote_number: 'CN-001' } });
        const callbacks = makeCallbacks();
        await flushOutbox(callbacks);

        expect((await db.outbox.toArray())[0].status).toBe('synced');
        expect(callbacks.onSynced).toHaveBeenCalledOnce();
    });
});

describe('flushOutbox on a shared device', () => {
    it("only sends the signed-in user's scans", async () => {
        await db.outbox.add(makeEntry({ client_event_id: 'mine', user_id: 1 }));
        await db.outbox.add(
            makeEntry({ client_event_id: 'theirs', user_id: 2 }),
        );
        mockFetch(201, { data: {} });

        await flushOutbox(makeCallbacks(), 1);

        const byId = Object.fromEntries(
            (await db.outbox.toArray()).map((e) => [
                e.client_event_id,
                e.status,
            ]),
        );
        expect(byId).toEqual({ mine: 'synced', theirs: 'pending' });
        expect(fetch).toHaveBeenCalledOnce();
    });
});

describe('flushOutbox with runner drop-offs', () => {
    const drop = (overrides: Partial<OutboxEntry> = {}) =>
        makeEntry({
            kind: 'unload_confirm',
            manifest_id: undefined,
            location_id: 'bay-uuid',
            client_event_id: '00000000-0000-4000-8000-0000000000d1',
            ...overrides,
        });

    it('posts a drop to the confirm endpoint with where it was put', async () => {
        await db.outbox.add(drop());
        mockFetch(200, { data: { progress: {} } });

        const callbacks = makeCallbacks();
        await flushOutbox(callbacks, undefined, 'unload_confirm');

        const [url, init] = (fetch as ReturnType<typeof vi.fn>).mock
            .calls[0] as [string, RequestInit];
        expect(url).toBe('/unload/confirm');
        expect(JSON.parse(init.body as string)).toMatchObject({
            barcode: 'BARCODE-001',
            location_id: 'bay-uuid',
            queued: false,
        });
        expect(callbacks.onSynced).toHaveBeenCalledOnce();
    });

    it('marks a drop as queued when it was made offline', async () => {
        await db.outbox.add(drop({ queued: true }));
        mockFetch(200, { data: {} });

        await flushOutbox(makeCallbacks(), undefined, 'unload_confirm');

        const [, init] = (fetch as ReturnType<typeof vi.fn>).mock.calls[0] as [
            string,
            RequestInit,
        ];
        expect(JSON.parse(init.body as string).queued).toBe(true);
    });

    it('marks a drop as queued once it has been tried and failed', async () => {
        await db.outbox.add(drop({ retry_count: 1 }));
        mockFetch(200, { data: {} });

        await flushOutbox(makeCallbacks(), undefined, 'unload_confirm');

        const [, init] = (fetch as ReturnType<typeof vi.fn>).mock.calls[0] as [
            string,
            RequestInit,
        ];
        expect(JSON.parse(init.body as string).queued).toBe(true);
    });

    it('reports a drop the server could not record as a warning', async () => {
        await db.outbox.add(drop());
        mockFetch(409, {
            error: {
                code: 'handling_unit_already_loaded',
                message: 'On a trailer',
            },
        });

        const callbacks = makeCallbacks();
        await flushOutbox(callbacks, undefined, 'unload_confirm');

        expect(callbacks.onWarning).toHaveBeenCalledWith(
            expect.anything(),
            'handling_unit_already_loaded',
            expect.anything(),
        );
    });

    it('only sends the kind it was asked to flush', async () => {
        await db.outbox.add(makeEntry({ client_event_id: 'scan-1' }));
        await db.outbox.add(drop());
        mockFetch(200, { data: {} });

        await flushOutbox(makeCallbacks(), undefined, 'unload_confirm');

        const urls = (fetch as ReturnType<typeof vi.fn>).mock.calls.map(
            (call) => call[0],
        );
        expect(urls).toEqual(['/unload/confirm']);

        const scan = await db.outbox
            .where('client_event_id')
            .equals('scan-1')
            .first();
        expect(scan?.status).toBe('pending');
    });

    it('treats an entry saved before kinds existed as a scan', async () => {
        await db.outbox.add(makeEntry({ kind: undefined }));
        mockFetch(201, { data: {} });

        await flushOutbox(makeCallbacks());

        expect((fetch as ReturnType<typeof vi.fn>).mock.calls[0][0]).toBe(
            '/loading/manifests/manifest-uuid/scan',
        );
    });
});
