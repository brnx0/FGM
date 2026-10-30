<?php

namespace App\Models;

use Database\Factories\AvaliadorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Avaliador cadastrado na retaguarda (Cadastro). Não se exclui, só se desativa. CPF com os 11 dígitos, sem pontuação.
 */
class Avaliador extends Model
{
    /** @use HasFactory<AvaliadorFactory> */
    use HasFactory;

    protected $table = 'avaliadores';

    protected $fillable = ['nome', 'data_nascimento', 'cpf', 'email', 'ativo'];

    protected function casts(): array
    {
        return [
            'data_nascimento' => 'date',
            'ativo' => 'boolean',
        ];
    }
}
