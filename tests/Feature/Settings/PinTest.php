<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('lets a user without a PIN set one', function () {
    $user = User::factory()->create(['pin' => null]);

    $this->actingAs($user)
        ->put('/settings/pin', ['pin' => '4321', 'pin_confirmation' => '4321'])
        ->assertSessionHasNoErrors();

    expect(Hash::check('4321', $user->fresh()->pin))->toBeTrue();
});

it('requires the current PIN to change an existing one', function () {
    $user = User::factory()->withPin('1234')->create();

    $this->actingAs($user)
        ->put('/settings/pin', ['current_pin' => '0000', 'pin' => '4321', 'pin_confirmation' => '4321'])
        ->assertSessionHasErrors('current_pin');

    expect(Hash::check('1234', $user->fresh()->pin))->toBeTrue();

    $this->actingAs($user)
        ->put('/settings/pin', ['current_pin' => '1234', 'pin' => '4321', 'pin_confirmation' => '4321'])
        ->assertSessionHasNoErrors();

    expect(Hash::check('4321', $user->fresh()->pin))->toBeTrue();
});

it('checks the new PIN format and confirmation', function () {
    $user = User::factory()->create(['pin' => null]);

    $this->actingAs($user)->put('/settings/pin', ['pin' => 'abcd', 'pin_confirmation' => 'abcd'])->assertSessionHasErrors('pin');
    $this->actingAs($user)->put('/settings/pin', ['pin' => '1234', 'pin_confirmation' => '4321'])->assertSessionHasErrors('pin');
});

it('shows whether a PIN is set', function () {
    $user = User::factory()->withPin()->create(['username' => 'jason.miller']);

    $this->actingAs($user)->get('/settings/pin')->assertOk();
});
