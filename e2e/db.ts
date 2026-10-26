import { execFileSync } from 'node:child_process';
import { appEnv } from '../playwright.config';

/**
 * Runs PHP in the app's context against the e2e database and returns what it
 * `return`s, decoded from JSON. Slow (a few hundred ms), so use it for setup
 * and for asserting what the server really stored, not in loops.
 */
export function php<T>(code: string): T {
    const output = execFileSync(
        'php',
        [
            'artisan',
            'tinker',
            '--execute',
            `echo "@@" . json_encode((function () { ${code} })()) . "@@";`,
        ],
        { env: { ...process.env, ...appEnv }, encoding: 'utf8' },
    );

    const match = output.match(/@@(.*)@@/s);

    if (!match) {
        throw new Error(`No result from PHP:\n${output}`);
    }

    return JSON.parse(match[1]) as T;
}

/**
 * The next clean pallet from the suite's own seed (E2E-0001 and up): pending,
 * unplaced, not on any manifest, from a sender with no holding rule, bound for
 * Sydney, so scanning it raises no warnings.
 */
export function cleanPallet(): { barcode: string; sender: string } {
    return php<{ barcode: string; sender: string }>(`
        $unit = App\\Models\\HandlingUnit::query()
            ->with('consignment')
            ->where('barcode', 'like', 'E2E-%')
            ->where('current_status', 'pending')
            ->whereNull('current_location_id')
            ->doesntHave('manifestItem')
            ->orderBy('barcode')
            ->firstOrFail();

        return ['barcode' => $unit->barcode, 'sender' => $unit->consignment->sender_name];
    `);
}

/** An open manifest that nobody has finished loading. */
export function openManifestId(): string {
    return php<string>(`
        return App\\Models\\Manifest::query()
            ->where('status', 'open')
            ->whereNull('loading_finished_at')
            ->orderBy('manifest_number')
            ->value('id');
    `);
}

export function eventsFor(barcode: string, types: string[]): number {
    return php<number>(`
        $unit = App\\Models\\HandlingUnit::where('barcode', '${barcode}')->firstOrFail();

        return App\\Models\\OperationalEvent::query()
            ->where('handling_unit_id', $unit->getKey())
            ->whereIn('event_type', ${JSON.stringify(types)})
            ->count();
    `);
}

export function manifestItemsFor(barcode: string): number {
    return php<number>(`
        $unit = App\\Models\\HandlingUnit::where('barcode', '${barcode}')->firstOrFail();

        return App\\Models\\ManifestItem::where('handling_unit_id', $unit->getKey())->count();
    `);
}

export function addHoldingRule(sender: string): void {
    php<boolean>(`
        App\\Models\\HoldingRule::factory()->create([
            'sender_name' => '${sender.replace(/'/g, "\\'")}',
            'base_lane' => 3,
            'is_active' => true,
        ]);

        return true;
    `);
}

export function removeHoldingRule(sender: string): void {
    php<boolean>(`
        App\\Models\\HoldingRule::where('sender_name', '${sender.replace(/'/g, "\\'")}')->delete();

        return true;
    `);
}

/** The most recent move recorded for a pallet. */
export function lastMove(barcode: string): {
    type: string;
    to: string;
    overrideReason: string | null;
    suggested: string | null;
} {
    return php(`
        $unit = App\\Models\\HandlingUnit::where('barcode', '${barcode}')->firstOrFail();
        $event = App\\Models\\OperationalEvent::query()
            ->where('handling_unit_id', $unit->getKey())
            ->whereIn('event_type', ['staged', 'moved'])
            ->latest('received_at')
            ->firstOrFail();

        return [
            'type' => $event->event_type->value,
            'to' => $event->metadata['to_location_code'],
            'overrideReason' => $event->metadata['override_reason'] ?? null,
            'suggested' => $event->metadata['suggested_location_code'] ?? null,
        ];
    `);
}
