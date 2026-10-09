<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

use function Laravel\Prompts\password;

class SetUserPin extends Command
{
    protected $signature = 'freight:set-pin
        {user : The user\'s email or username}
        {--username= : Set the username used to sign in}
        {--pin= : The PIN, 4 to 6 digits (prompted when omitted)}';

    protected $description = 'Set a user\'s warehouse login username and PIN';

    public function handle(): int
    {
        $identifier = (string) $this->argument('user');

        $user = User::query()
            ->where('email', $identifier)
            ->orWhere('username', strtolower($identifier))
            ->first();

        if (! $user) {
            $this->error("No user found for [{$identifier}].");

            return self::FAILURE;
        }

        $pin = $this->option('pin') ?? password('PIN (4 to 6 digits)');

        if (! preg_match('/^\d{4,6}$/', (string) $pin)) {
            $this->error('A PIN is 4 to 6 digits.');

            return self::FAILURE;
        }

        $username = $this->option('username');

        if ($username !== null) {
            $username = strtolower(trim($username));

            if (User::query()->where('username', $username)->whereKeyNot($user->getKey())->exists()) {
                $this->error("The username [{$username}] is already taken.");

                return self::FAILURE;
            }

            $user->username = $username;
        }

        if ($user->username === null) {
            $this->error('This user has no username yet. Pass --username.');

            return self::FAILURE;
        }

        $user->forceFill(['pin' => $pin])->save();

        $this->info("PIN set for {$user->username}.");

        return self::SUCCESS;
    }
}
