<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('sets a username and PIN by email', function () {
    $user = User::factory()->create(['email' => 'jason@example.com', 'username' => null]);

    $this->artisan('freight:set-pin', ['user' => 'jason@example.com', '--username' => 'Jason.Miller', '--pin' => '2468'])
        ->expectsOutput('PIN set for jason.miller.')
        ->assertSuccessful();

    $user->refresh();

    expect($user->username)->toBe('jason.miller')
        ->and(Hash::check('2468', $user->pin))->toBeTrue();
});

it('finds the user by username to change just the PIN', function () {
    $user = User::factory()->create(['username' => 'jason.miller']);

    $this->artisan('freight:set-pin', ['user' => 'jason.miller', '--pin' => '1357'])->assertSuccessful();

    expect(Hash::check('1357', $user->fresh()->pin))->toBeTrue();
});

it('refuses a bad PIN, an unknown user, or a taken username', function () {
    $user = User::factory()->create(['username' => 'jason.miller']);
    User::factory()->create(['username' => 'taken']);

    $this->artisan('freight:set-pin', ['user' => 'jason.miller', '--pin' => '12'])->assertFailed();
    $this->artisan('freight:set-pin', ['user' => 'nobody', '--pin' => '1234'])->assertFailed();
    $this->artisan('freight:set-pin', ['user' => 'jason.miller', '--username' => 'taken', '--pin' => '1234'])->assertFailed();

    expect($user->fresh()->pin)->toBeNull();
});

it('needs a username before a PIN can work', function () {
    User::factory()->create(['email' => 'a@example.com', 'username' => null]);

    $this->artisan('freight:set-pin', ['user' => 'a@example.com', '--pin' => '1234'])->assertFailed();
});
