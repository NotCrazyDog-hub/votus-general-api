<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

#[Signature('admin:create-user {email?} {--name=} {--password=}')]
#[Description('Cria (ou promove) um usuário administrador do painel. Não há cadastro público — só por aqui.')]
class CreateAdminUser extends Command
{
    public function handle(): int
    {
        $email = $this->argument('email') ?? $this->ask('E-mail do administrador');
        $name = $this->option('name') ?? $this->ask('Nome', 'Administrador Votus');
        $password = $this->option('password') ?? $this->secret('Senha (mín. 8 caracteres)');

        $validator = Validator::make(
            ['email' => $email, 'password' => $password],
            ['email' => ['required', 'email'], 'password' => ['required', 'string', 'min:8']]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $erro) {
                $this->error($erro);
            }

            return self::FAILURE;
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make($password), 'is_admin' => true]
        );

        $this->info("Administrador '{$user->email}' pronto para uso no painel.");

        return self::SUCCESS;
    }
}
