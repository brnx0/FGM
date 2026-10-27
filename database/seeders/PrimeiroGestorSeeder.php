<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Papel administrador (em todo ambiente) e, fora de produção, o primeiro administrador.
 * Idempotente: não duplica nem redefine a senha de quem já existe, mesmo que ele tenha trocado o e-mail.
 */
class PrimeiroGestorSeeder extends Seeder
{
    /** CPF fictício que identifica o primeiro administrador. */
    private const string CPF_DO_PRIMEIRO_ADMINISTRADOR = '00000000000';

    public function run(): void
    {
        $papel = Role::firstOrCreate(
            ['slug' => Role::ADMINISTRADOR],
            ['nome' => 'Administrador'],
        );

        if (app()->environment('production')) {
            $this->command?->warn('PrimeiroGestorSeeder: em produção só o papel administrador é criado.');

            return;
        }

        $admin = User::where('cpf', self::CPF_DO_PRIMEIRO_ADMINISTRADOR)->first()
            ?? User::firstOrCreate(
                ['email' => 'admin@admin'],
                [
                    'cpf' => self::CPF_DO_PRIMEIRO_ADMINISTRADOR,
                    'name' => 'Administrador',
                    'password' => '123456',
                ],
            );

        $admin->papeis()->syncWithoutDetaching([$papel->id]);
    }
}
