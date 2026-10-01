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
                            {--email= : Email администратора}
                            {--name= : Ник администратора}
                            {--password= : Пароль (если не указан — генерируется)}';

    protected $description = 'Create the first administrator account (production-safe).';

    public function handle(NicknameSuggester $suggester): int
    {
        $email = (string) ($this->option('email') ?? $this->ask('Email администратора'));
        $name = (string) ($this->option('name') ?? $this->ask('Ник администратора'));
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
            $this->error('Ник не соответствует формату (3–24 символа, латиница/цифры/-/_ , начинается с буквы).');
            return self::FAILURE;
        }

        if ($suggester->isBlacklisted($name)) {
            $this->error('Этот ник в чёрном списке.');
            return self::FAILURE;
        }

        if (User::query()->where('name', $name)->exists()) {
            $this->error("Ник '{$name}' уже занят.");
            return self::FAILURE;
        }

        if (User::query()->where('email', $email)->exists()) {
            $this->error("Email '{$email}' уже занят.");
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
            $this->error('Ошибка создания: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Администратор создан успешно.');
        $this->newLine();
        $this->line('  ID:     <fg=yellow>' . $user->id . '</>');
        $this->line('  Ник:    <fg=yellow>' . $user->name . '</>');
        $this->line('  Email:  <fg=yellow>' . $user->email . '</>');
        $this->line('  Пароль: <fg=red;options=bold>' . $password . '</>');
        $this->newLine();
        $this->warn('Сохраните пароль в защищённом месте. Он больше не будет показан.');
        $this->newLine();

        return self::SUCCESS;
    }

    protected function generatePassword(): string
    {
        $alphabet = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%^&*';

        return substr(str_shuffle(str_repeat($alphabet, 4)), 0, 16);
    }
}
