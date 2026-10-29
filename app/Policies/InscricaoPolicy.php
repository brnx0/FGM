<?php

namespace App\Policies;

use App\Models\Inscricao;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class InscricaoPolicy
{
    /**
     * Só quem criou a inscrição abre, edita e mexe nela. Para os outros ela não existe (404), assim como
     * para a inscrição cujo edital foi apagado.
     */
    public function gerenciar(User $user, Inscricao $inscricao): Response
    {
        return (int) $inscricao->user_id === (int) $user->id && $inscricao->edital !== null
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
