import 'fake-indexeddb/auto';
import { afterEach } from 'vitest';

afterEach(async () => {
    const { db } = await import('@/lib/outbox');
    await db.outbox.clear();
});
