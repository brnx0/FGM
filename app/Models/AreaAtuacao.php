<?php

namespace App\Models;

use Database\Factories\AreaAtuacaoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Área de atuação cultural (ex.: Audiovisual, Teatro), cadastrada na retaguarda (Parametrização). Não se exclui, só se
 * desativa: a lista vai ser escolhida nos editais e nas inscrições.
 */
class AreaAtuacao extends Model
{
    /** @use HasFactory<AreaAtuacaoFactory> */
    use HasFactory;

    protected $table = 'areas_atuacao';

    protected $fillable = ['nome', 'ativo'];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }
}
