import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';
import {
    addHoldingRule,
    cleanPallet,
    eventsFor,
    lastMove,
    manifestItemsFor,
    openManifestId,
    removeHoldingRule,
    twoOpenManifestIds,
} from './db';

const MOVES = ['staged', 'moved'];

/** Scan a pallet at Unload (online) and return the bay it is sent to. */
async function directedToBay(page: Page, barcode: string): Promise<string> {
    await page.goto('/unload');
    await page.getByPlaceholder('Barcode').fill(barcode);
    await page.getByRole('button', { name: 'Find bay' }).click();
    await expect(page.getByText('Take it to')).toBeVisible();

    const label = await confirmButton(page).getAttribute('aria-label');

    return label!.replace('Tap when dropped off in ', '');
}

/** What the device's queue holds, as `status/retries` per entry. */
async function queued(page: Page): Promise<string[]> {
    return page.evaluate(
        () =>
            new Promise<string[]>((resolve) => {
                const open = indexedDB.open('freight-ops-outbox');

                open.onsuccess = () => {
                    const rows = open.result
                        .transaction('outbox')
                        .objectStore('outbox')
                        .getAll();

                    rows.onsuccess = () =>
                        resolve(
                            rows.result.map(
                                (row: {
                                    status: string;
                                    retry_count: number;
                                }) => `${row.status}/${row.retry_count}`,
                            ),
                        );
                };
            }),
    );
}

/** Leave a scan in the device's queue, as if it had been made offline earlier. */
async function leaveQueuedScan(
    page: Page,
    manifestId: string,
    barcode: string,
): Promise<void> {
    await page.evaluate(
        ({ manifestId, barcode }) =>
            new Promise<void>((resolve, reject) => {
                const open = indexedDB.open('freight-ops-outbox');

                open.onsuccess = () => {
                    const tx = open.result.transaction('outbox', 'readwrite');

                    tx.objectStore('outbox').add({
                        kind: 'scan',
                        client_event_id: crypto.randomUUID(),
                        manifest_id: manifestId,
                        barcode,
                        occurred_at: new Date().toISOString(),
                        acknowledged_warnings: [],
                        status: 'pending',
                        retry_count: 0,
                        created_at: Date.now(),
                    });
                    tx.oncomplete = () => resolve();
                    tx.onerror = () => reject(tx.error);
                };
            }),
        { manifestId, barcode },
    );
}

const confirmButton = (page: Page) =>
    page.getByRole('button', { name: /Tap when dropped off in/ });

test.describe('runner drop-offs', () => {
    test('a drop confirmed offline is kept on the device and sent when the signal returns', async ({
        page,
        context,
    }) => {
        const { barcode } = cleanPallet();
        const bay = await directedToBay(page, barcode);

        await context.setOffline(true);
        await confirmButton(page).click();

        await expect(page.getByText('Saved on this device')).toBeVisible();
        await expect(page.getByText('1 drop waiting to sync.')).toBeVisible();
        expect(eventsFor(barcode, MOVES)).toBe(0);

        await context.setOffline(false);

        await expect(page.getByText(/in bay|items placed/)).toBeVisible();
        await expect(page.getByText('waiting to sync')).toBeHidden();
        expect(eventsFor(barcode, MOVES)).toBe(1);
        expect(lastMove(barcode)).toMatchObject({
            to: bay,
            overrideReason: null,
        });
    });

    test('a drop made while the bay rules change is recorded where the pallet was put', async ({
        page,
        context,
    }) => {
        const { barcode, sender } = cleanPallet();
        const bay = await directedToBay(page, barcode);

        await context.setOffline(true);
        // While the runner is out of signal, the sender is given a holding rule.
        addHoldingRule(sender);

        try {
            await confirmButton(page).click();
            await expect(page.getByText('Saved on this device')).toBeVisible();

            await context.setOffline(false);
            await expect(page.getByText('waiting to sync')).toBeHidden();

            expect(lastMove(barcode)).toMatchObject({
                to: bay,
                overrideReason: 'offline_stale_direction',
            });
            await expect(page.getByText('could not be recorded')).toBeHidden();
        } finally {
            removeHoldingRule(sender);
        }
    });

    test('a drop survives a long outage with the browser still "online"', async ({
        page,
    }) => {
        // A captive portal or a dead access point: the browser thinks it is
        // online but every request fails. This used to burn the retry limit.
        await page.clock.install();

        const { barcode } = cleanPallet();
        await directedToBay(page, barcode);

        await page.route('**/unload/confirm', (route) =>
            route.abort('connectionfailed'),
        );
        await confirmButton(page).click();
        await expect(page.getByText('Saved on this device')).toBeVisible();

        // Far more retry ticks than the limit.
        for (let tick = 0; tick < 8; tick++) {
            await page.clock.runFor(16_000);
        }

        // Still waiting, with no retry spent: the connection is not its fault.
        await expect.poll(() => queued(page)).toEqual(['pending/0']);
        await expect(page.getByText('could not be delivered')).toBeHidden();
        await expect(page.getByText('1 drop waiting to sync.')).toBeVisible();
        expect(eventsFor(barcode, MOVES)).toBe(0);

        await page.unroute('**/unload/confirm');
        await page.clock.runFor(16_000);

        await expect(page.getByText('waiting to sync')).toBeHidden();
        expect(eventsFor(barcode, MOVES)).toBe(1);
    });
});

test.describe('loader scans', () => {
    test('scanning the same pallet twice offline loads it once', async ({
        page,
        context,
    }) => {
        const manifestId = openManifestId();
        const { barcode } = cleanPallet();

        await page.goto(`/loading/manifests/${manifestId}`);
        await expect(page.getByPlaceholder('Barcode')).toBeVisible();

        await context.setOffline(true);

        await page.getByPlaceholder('Barcode').fill(barcode);
        await page.getByRole('button', { name: 'Load', exact: true }).click();
        await expect(page.getByText('1 scan waiting to sync.')).toBeVisible();

        await page.getByRole('button', { name: 'Load', exact: true }).click();
        await expect(page.getByText('2 scans waiting to sync.')).toBeVisible();
        expect(manifestItemsFor(barcode)).toBe(0);

        await context.setOffline(false);

        await expect(page.getByText('waiting to sync')).toBeHidden();
        expect(manifestItemsFor(barcode)).toBe(1);
        expect(eventsFor(barcode, ['loaded'])).toBe(1);
    });
});

test.describe('a queue left from an earlier visit', () => {
    test('is sent when the manifest page opens', async ({ page }) => {
        const [manifestId] = twoOpenManifestIds();
        const { barcode } = cleanPallet();

        await page.goto(`/loading/manifests/${manifestId}`);
        await expect(page.getByPlaceholder('Barcode')).toBeVisible();

        await leaveQueuedScan(page, manifestId, barcode);
        expect(manifestItemsFor(barcode)).toBe(0);

        await page.reload();

        await expect.poll(() => manifestItemsFor(barcode)).toBe(1);
        await expect(page.getByText('waiting to sync')).toBeHidden();
    });

    test('leaves scans for another manifest alone', async ({ page }) => {
        const [manifestId, otherId] = twoOpenManifestIds();
        const { barcode } = cleanPallet();

        await page.goto(`/loading/manifests/${manifestId}`);
        await expect(page.getByPlaceholder('Barcode')).toBeVisible();

        await leaveQueuedScan(page, otherId, barcode);
        await page.reload();
        await expect(page.getByPlaceholder('Barcode')).toBeVisible();

        // Nothing here can show that scan's result, so it stays queued and
        // does not claim to be waiting on this manifest.
        await expect.poll(() => queued(page)).toEqual(['pending/0']);
        await expect(page.getByText('waiting to sync')).toBeHidden();
        expect(manifestItemsFor(barcode)).toBe(0);
    });
});

test.describe('the screen', () => {
    test('does not move the Confirm button when the signal drops', async ({
        page,
        context,
    }) => {
        const { barcode } = cleanPallet();
        await directedToBay(page, barcode);

        const before = await confirmButton(page).boundingBox();
        await context.setOffline(true);
        await expect(page.getByText('Offline —')).toBeVisible();
        const after = await confirmButton(page).boundingBox();

        expect(after).toEqual(before);
    });
});
