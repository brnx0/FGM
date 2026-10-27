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
            'phone' => $id->telefone,
        ]);

        $email = $id->email !== null && mb_strlen($id->email) <= 255 ? $id->email : null;
        if (! $this->emailPertenceAOutro($email, $user)) {
            $user->fill(['email' => $email, 'email_verified' => $id->emailVerificado]);
        }
        $user->govbr_trust_level = $id->nivelConfiabilidade ?? $user->govbr_trust_level;
        $user->last_login_at = now();
        $user->save();

        Auth::login($user);
        session()->regenerate();

        return $user;
    }

    /** Login nunca falha por e-mail: se já é de outro usuário, o e-mail atual é mantido. */
    private function emailPertenceAOutro(?string $email, User $user): bool
    {
        $normalizado = $email === null ? '' : mb_strtolower(trim($email));
        if ($normalizado === '') {
            return false;
        }

        return User::where('email', $normalizado)
            ->when($user->exists, fn ($q) => $q->whereKeyNot($user->getKey()))
            ->exists();
    }

    public function destinoPosLogin(User $user): string
    {
        return $user->profile_completed_at === null ? route('cadastro') : route('home');
    }
}
