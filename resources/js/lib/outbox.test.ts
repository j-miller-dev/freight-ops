import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { db, flushOutbox, MAX_RETRIES } from '@/lib/outbox';
import type { FlushCallbacks, OutboxEntry } from '@/lib/outbox';

function makeEntry(overrides: Partial<OutboxEntry> = {}): Omit<OutboxEntry, 'id'> {
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

function makeCallbacks(overrides: Partial<FlushCallbacks> = {}): FlushCallbacks {
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
                details: { destination_code: 'MEL', manifest_number: 'MAN-001' },
            },
        });

        const callbacks = makeCallbacks();
        await flushOutbox(callbacks);

        const entries = await db.outbox.toArray();
        expect(entries[0].status).toBe('synced');
        expect(callbacks.onWarning).toHaveBeenCalledWith(
            expect.objectContaining({ barcode: 'BARCODE-001' }),
            'destination_mismatch',
            expect.objectContaining({ error: expect.objectContaining({ code: 'destination_mismatch' }) }),
        );
        expect(callbacks.onSynced).not.toHaveBeenCalled();
    });

    it('increments retry_count and keeps entry pending on network error', async () => {
        await db.outbox.add(makeEntry());
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Failed to fetch')));

        const callbacks = makeCallbacks();
        await flushOutbox(callbacks);

        const entries = await db.outbox.toArray();
        expect(entries[0].status).toBe('pending');
        expect(entries[0].retry_count).toBe(1);
        expect(callbacks.onError).toHaveBeenCalledWith(
            expect.anything(),
            'Network error',
        );
    });

    it('marks entry failed after MAX_RETRIES network errors', async () => {
        await db.outbox.add(makeEntry({ retry_count: MAX_RETRIES - 1 }));
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Failed to fetch')));

        const callbacks = makeCallbacks();
        await flushOutbox(callbacks);

        const entries = await db.outbox.toArray();
        expect(entries[0].status).toBe('failed');
        expect(entries[0].retry_count).toBe(MAX_RETRIES);
    });

    it('preserves occurred_at through the outbox without mutation', async () => {
        const occurred_at = '2026-10-09T08:00:00.000Z';
        await db.outbox.add(makeEntry({ occurred_at }));

        let capturedBody: Record<string, unknown> | null = null;
        vi.stubGlobal(
            'fetch',
            vi.fn().mockImplementation((_url: string, init: RequestInit) => {
                capturedBody = JSON.parse(init.body as string) as Record<string, unknown>;

                return Promise.resolve(
                    new Response(JSON.stringify({ data: {} }), {
                        status: 201,
                        headers: { 'Content-Type': 'application/json' },
                    }),
                );
            }),
        );

        await flushOutbox(makeCallbacks());

        expect((capturedBody as Record<string, unknown> | null)?.occurred_at).toBe(occurred_at);
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
