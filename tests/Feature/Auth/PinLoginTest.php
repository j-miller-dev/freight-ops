<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(fn () => RateLimiter::clear('jason.miller|127.0.0.1'));

it('shows the PIN pad at /login and password login at /login/password', function () {
    $this->get('/login')->assertInertia(fn (Assert $page) => $page->component('auth/login'));
    $this->get('/login/password')->assertInertia(fn (Assert $page) => $page->component('auth/password-login'));
});

it('signs in with a username and PIN', function () {
    $user = User::factory()->withPin('1234')->create(['username' => 'jason.miller']);

    $this->post('/login/pin', ['username' => 'jason.miller', 'pin' => '1234'])
        ->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
});

it('ignores case and surrounding spaces in the username', function () {
    $user = User::factory()->withPin('123456')->create(['username' => 'jason.miller']);

    $this->post('/login/pin', ['username' => '  Jason.Miller ', 'pin' => '123456'])->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
});

it('gives the same answer for a wrong PIN and an unknown username', function () {
    User::factory()->withPin('1234')->create(['username' => 'jason.miller']);

    $wrongPin = $this->from('/login')->post('/login/pin', ['username' => 'jason.miller', 'pin' => '9999']);
    $unknown = $this->from('/login')->post('/login/pin', ['username' => 'nobody', 'pin' => '1234']);

    $wrongPin->assertSessionHasErrors(['pin' => "That username and PIN don't match."]);
    $unknown->assertSessionHasErrors(['pin' => "That username and PIN don't match."]);
    $this->assertGuest();
});

it('rejects a malformed PIN', function (string $pin) {
    $this->from('/login')->post('/login/pin', ['username' => 'jason.miller', 'pin' => $pin])
        ->assertSessionHasErrors('pin');
})->with(['123', '1234567', 'abcd', '12 34']);

it('does not let a user without a PIN in', function () {
    User::factory()->create(['username' => 'jason.miller', 'pin' => null]);

    $this->from('/login')->post('/login/pin', ['username' => 'jason.miller', 'pin' => '1234'])
        ->assertSessionHasErrors('pin');
    $this->assertGuest();
});

it('locks out after five wrong PINs, even for the right one', function () {
    User::factory()->withPin('1234')->create(['username' => 'jason.miller']);

    foreach (range(1, 5) as $ignored) {
        $this->from('/login')->post('/login/pin', ['username' => 'jason.miller', 'pin' => '0000']);
    }

    $response = $this->from('/login')->post('/login/pin', ['username' => 'jason.miller', 'pin' => '1234']);

    $response->assertSessionHasErrors('pin');
    expect(session('errors')->first('pin'))->toStartWith('Too many attempts');
    $this->assertGuest();
});

it('does not bypass two-factor authentication', function () {
    User::factory()->withPin('1234')->withTwoFactor()->create(['username' => 'jason.miller']);

    $this->from('/login')->post('/login/pin', ['username' => 'jason.miller', 'pin' => '1234'])
        ->assertSessionHasErrors('pin');
    $this->assertGuest();
});

it('keeps signed-in users away from the login routes', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/login/password')->assertRedirect();
    $this->post('/login/pin', ['username' => 'x', 'pin' => '1234'])->assertRedirect();
});

it('signs in over JSON for a silent re-login', function () {
    $user = User::factory()->withPin('1234')->create(['username' => 'jason.miller']);

    $this->postJson('/login/pin', ['username' => 'jason.miller', 'pin' => '1234'])
        ->assertOk()
        ->assertJsonPath('data.id', $user->getKey());

    $this->assertAuthenticatedAs($user);
});

it('answers a wrong PIN over JSON with a 422', function () {
    User::factory()->withPin('1234')->create(['username' => 'jason.miller']);

    $this->postJson('/login/pin', ['username' => 'jason.miller', 'pin' => '0000'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.pin.0', "That username and PIN don't match.");
});

it('offers a session ping to guests', function () {
    $this->get('/session/ping')->assertNoContent();
});
