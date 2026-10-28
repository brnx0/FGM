<?php

namespace App\Actions;

use App\Auth\GovBr\GovBrIdentity;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/** Entrada no portal do proponente pelo gov.br. A retaguarda nunca é aberta por aqui: ela tem login próprio. */
class LoginGovBrUser
{
    public const GESTOR_RECUSADO = 'Este CPF é de um usuário da retaguarda e não entra pelo portal. Use a tela de login da retaguarda.';

    /** Gestor (ativo e com papel) não entra: devolve null sem mexer na sessão nem nos dados dele. */
    public function handle(GovBrIdentity $id): ?User
    {
        $user = User::firstOrNew(['cpf' => $id->cpf]);

        if ($user->exists && $user->ehGestor()) {
            return null;
        }

        // Usuário da retaguarda desativado entra como proponente, mas mantém o nome e o e-mail cadastrados lá.
        $daRetaguarda = $user->exists && $user->papeis()->exists();

        if (! $daRetaguarda) {
            $user->name = mb_substr($id->nome, 0, 255);
        }
        $user->phone = $id->telefone;

        $email = $id->email !== null && mb_strlen($id->email) <= 255 ? $id->email : null;
        if (! $daRetaguarda && ! $this->emailPertenceAOutro($email, $user)) {
            $user->fill(['email' => $email, 'email_verified' => $id->emailVerificado]);
        }
        $user->govbr_trust_level = $id->nivelConfiabilidade ?? $user->govbr_trust_level;
        $user->last_login_at = now();
        $user->save();

        Auth::login($user);
        session()->regenerate();
        session(['portal.via_govbr' => true]);

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

    /** Cadastro não finalizado volta ao formulário; finalizado abre o portal do tipo escolhido. */
    public function destinoPosLogin(User $user): string
    {
        if (! $user->cadastroFinalizado()) {
            return route('cadastro');
        }

        return route($user->rotaInicialDoPortal());
    }
}
