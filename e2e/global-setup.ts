import { execFileSync } from 'node:child_process';
import { appEnv } from '../playwright.config';

// A known database for every run: migrated and seeded with the dev users
// (PIN 1234), a Sydney freight day, and the suite's own clean pallets.
export default function globalSetup() {
    const options = {
        env: { ...process.env, ...appEnv },
        stdio: 'inherit',
    } as const;

    execFileSync(
        'php',
        ['artisan', 'migrate:fresh', '--seed', '--force', '--no-interaction'],
        options,
    );
    execFileSync(
        'php',
        [
            'artisan',
            'db:seed',
            '--class=E2eSeeder',
            '--force',
            '--no-interaction',
        ],
        options,
    );
}
