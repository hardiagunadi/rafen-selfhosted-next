<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateInitialSuperAdmin extends Command
{
    protected $signature = 'user:create-super-admin
        {name : Nama super admin}
        {email : Email super admin}
        {--password= : Password super admin}';

    protected $description = 'Buat akun super admin awal untuk instance self-hosted.';

    public function handle(): int
    {
        $password = (string) ($this->option('password') ?: Str::password(16));

        $user = User::query()->updateOrCreate(
            ['email' => (string) $this->argument('email')],
            [
                'name' => (string) $this->argument('name'),
                'password' => $password,
                'is_super_admin' => true,
                'email_verified_at' => now(),
            ],
        );

        $this->info('Super admin berhasil disiapkan.');
        $this->line('ID       : '.$user->id);
        $this->line('Email    : '.$user->email);
        $this->line('Password : '.$password);

        return self::SUCCESS;
    }
}