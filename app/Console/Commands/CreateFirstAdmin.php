<?php

namespace App\Console\Commands;

use App\Enums\UserStatus;
use App\Models\User;
use App\Services\NicknameSuggester;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CreateFirstAdmin extends Command
{
    protected $signature = 'app:create-admin
                            {--email= : Email of the administrator}
                            {--name= : Administrator nickname}
                            {--password= : Password (generated when omitted)}';

    protected $description = 'Create the first administrator account (production-safe).';

    public function handle(NicknameSuggester $suggester): int
    {
        $email = (string) ($this->option('email') ?? $this->ask(__('site.console.create_admin.ask_email')));
        $name = (string) ($this->option('name') ?? $this->ask(__('site.console.create_admin.ask_name')));
        $password = (string) ($this->option('password') ?? $this->generatePassword());

        $validator = Validator::make(
            ['email' => $email, 'name' => $name, 'password' => $password],
            [
                'email' => ['required', 'string', 'email:rfc', 'max:255'],
                'name' => ['required', 'string', 'min:3', 'max:24'],
                'password' => ['required', 'string', 'min:12'],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        if (! $suggester->passesFormat($name)) {
            $this->error(__('site.console.create_admin.invalid_name'));

            return self::FAILURE;
        }

        if ($suggester->isBlacklisted($name)) {
            $this->error(__('site.console.create_admin.blacklisted'));

            return self::FAILURE;
        }

        if (User::query()->where('name', $name)->exists()) {
            $this->error(__('site.console.create_admin.name_taken', ['name' => $name]));

            return self::FAILURE;
        }

        if (User::query()->where('email', $email)->exists()) {
            $this->error(__('site.console.create_admin.email_taken', ['email' => $email]));

            return self::FAILURE;
        }

        try {
            $user = DB::transaction(function () use ($name, $email, $password) {
                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    'password' => $password,
                    'status' => UserStatus::Active,
                    'pd_consent_at' => now(),
                    'pd_policy_version' => 'admin-bootstrap',
                    'is_profile_public' => false,
                ]);

                $user->forceFill(['email_verified_at' => now()])->save();
                $user->assignRole('admin');

                return $user;
            });
        } catch (\Throwable $e) {
            $this->error(__('site.console.create_admin.create_failed', ['error' => $e->getMessage()]));

            return self::FAILURE;
        }

        $this->newLine();
        $this->info(__('site.console.create_admin.created'));
        $this->newLine();
        $this->line('  '.__('site.console.create_admin.id').':     <fg=yellow>'.$user->id.'</>');
        $this->line('  '.__('site.console.create_admin.name').':    <fg=yellow>'.$user->name.'</>');
        $this->line('  '.__('site.console.create_admin.email').':  <fg=yellow>'.$user->email.'</>');
        $this->line('  '.__('site.console.create_admin.password').': <fg=red;options=bold>'.$password.'</>');
        $this->newLine();
        $this->warn(__('site.console.create_admin.save_password'));
        $this->newLine();

        return self::SUCCESS;
    }

    protected function generatePassword(): string
    {
        $alphabet = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%^&*';

        return substr(str_shuffle(str_repeat($alphabet, 4)), 0, 16);
    }
}
