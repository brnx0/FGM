<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Seeder;

/**
 * Papel administrador (em todo ambiente) e, fora de produção, o primeiro administrador.
 * Idempotente: só cria o primeiro administrador se ainda não houver nenhum, e nunca redefine senha.
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

        // Já há administrador (mesmo que o primeiro tenha trocado e-mail e CPF): nada a criar.
        if (User::whereHas('papeis', fn (Builder $q) => $q->whereKey($papel->id))->exists()) {
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
