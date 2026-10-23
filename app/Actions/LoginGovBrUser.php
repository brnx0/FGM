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
            'name' => mb_substr($id->nome, 0, 255),
            'email' => $id->email !== null && mb_strlen($id->email) <= 255 ? $id->email : null,
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
