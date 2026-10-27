<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class PrimeiroGestorSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('PrimeiroGestorSeeder ignorado em produção.');

            return;
        }

        $papel = Role::firstOrCreate(
            ['slug' => Role::ADMINISTRADOR],
            ['nome' => 'Administrador'],
        );

        $admin = User::firstOrCreate(
            ['email' => 'admin@admin'],
            [
                'cpf' => '00000000000',
                'name' => 'Administrador',
                'password' => '123456',
            ],
        );

        $admin->papeis()->syncWithoutDetaching([$papel->id]);
    }
}
