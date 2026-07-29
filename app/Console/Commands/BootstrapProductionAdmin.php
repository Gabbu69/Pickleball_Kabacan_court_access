<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BootstrapProductionAdmin extends Command
{
    protected $signature = 'app:bootstrap-production-admin
        {--rotate-password : Replace the password for an existing administrator}
        {--force : Permit execution outside the production environment}';

    protected $description = 'Idempotently create or reactivate the production administrator from environment secrets';

    public function handle(): int
    {
        if (! app()->environment('production') && ! $this->option('force')) {
            $this->error('This command is production-only. Use --force for an intentional non-production run.');

            return self::FAILURE;
        }

        $credentials = [
            'name' => (string) env('ADMIN_NAME', 'Kabacan PicklePlay Admin'),
            'email' => (string) env('ADMIN_EMAIL'),
            'phone' => env('ADMIN_PHONE'),
            'password' => (string) env('ADMIN_PASSWORD'),
        ];

        $validator = Validator::make($credentials, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'password' => ['required', 'string', 'min:12'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $created = false;

        DB::transaction(function () use ($credentials, &$created): void {
            $user = User::query()->where('email', $credentials['email'])->lockForUpdate()->first();

            if (! $user) {
                User::create([
                    'name' => $credentials['name'],
                    'email' => $credentials['email'],
                    'phone' => $credentials['phone'],
                    'password' => $credentials['password'],
                    'role' => UserRole::Admin,
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]);
                $created = true;

                return;
            }

            $updates = [
                'name' => $credentials['name'],
                'phone' => $credentials['phone'],
                'role' => UserRole::Admin,
                'status' => 'active',
                'closed_at' => null,
                'anonymized_reference' => null,
                'email_verified_at' => $user->email_verified_at ?: now(),
            ];

            if ($this->option('rotate-password')) {
                $updates['password'] = $credentials['password'];
            }

            $user->update($updates);
        });

        $this->info($created
            ? 'Production administrator created.'
            : 'Production administrator is active; no duplicate account was created.');

        return self::SUCCESS;
    }
}
