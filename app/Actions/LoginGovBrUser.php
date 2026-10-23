<?php

namespace App\Actions;

use App\Auth\GovBr\GovBrIdentity;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class LoginGovBrUser
{
    public function handle(GovBrIdentity $id): User
    {
        $user = User::firstOrNew(['cpf' => $id->cpf]);

        $user->fill([
            'name' => $id->nome,
            'email' => $id->email,
            'email_verified' => $id->emailVerificado,
            'phone' => $id->telefone,
        ]);
        $user->govbr_trust_level = $id->nivelConfiabilidade ?? $user->govbr_trust_level;
        $user->last_login_at = now();
        $user->save();

        Auth::login($user);
        session()->regenerate();

        return $user;
    }

    public function destinoPosLogin(User $user): string
    {
        return $user->profile_completed_at === null ? route('cadastro') : route('home');
    }
}
