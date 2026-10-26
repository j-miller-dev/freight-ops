import { expect, test as setup } from '@playwright/test';

// Sign in once with the seeded dev user's PIN and reuse the session.
setup('sign in with a PIN', async ({ request }) => {
    await request.get('/session/ping');

    const state = await request.storageState();
    const xsrf = state.cookies.find((cookie) => cookie.name === 'XSRF-TOKEN');

    const response = await request.post('/login/pin', {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': decodeURIComponent(xsrf?.value ?? ''),
        },
        data: { username: 'jason.miller', pin: '1234' },
    });

    expect(response.ok()).toBe(true);

    await request.storageState({ path: 'e2e/.auth/state.json' });
});
