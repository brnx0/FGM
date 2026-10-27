<?php

namespace App\Policies;

use App\Models\Organizacao;
use App\Models\User;

class OrganizacaoPolicy
{
    /** Quem tem vínculo vê e edita os dados da organização. */
    public function update(User $user, Organizacao $organizacao): bool
    {
        return $organizacao->temVinculo($user);
    }

    /** Só o responsável vincula e desvincula pessoas. */
    public function gerenciarVinculos(User $user, Organizacao $organizacao): bool
    {
        return $organizacao->ehResponsavel($user);
    }
}
