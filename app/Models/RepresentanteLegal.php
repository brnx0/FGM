<?php

namespace App\Models;

use App\Enums\OrigemRepresentacao;
use App\Enums\TipoDocumentoIdentidade;
use Database\Factories\RepresentanteLegalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepresentanteLegal extends Model
{
    /** @use HasFactory<RepresentanteLegalFactory> */
    use HasFactory;

    protected $table = 'representantes_legais';

    protected $fillable = [
        'organizacao_id', 'ordem', 'cpf', 'nome', 'tipo_documento', 'numero_documento',
        'cargo', 'telefone', 'email', 'origem', 'origem_descricao',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'ordem' => 'integer',
            'tipo_documento' => TipoDocumentoIdentidade::class,
            'origem' => OrigemRepresentacao::class,
        ];
    }

    /** @return BelongsTo<Organizacao, $this> */
    public function organizacao(): BelongsTo
    {
        return $this->belongsTo(Organizacao::class);
    }
}
