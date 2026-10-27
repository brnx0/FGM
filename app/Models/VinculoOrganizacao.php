<?php

namespace App\Models;

use Database\Factories\VinculoOrganizacaoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VinculoOrganizacao extends Model
{
    /** @use HasFactory<VinculoOrganizacaoFactory> */
    use HasFactory;

    protected $table = 'organizacao_vinculos';

    protected $fillable = ['organizacao_id', 'cpf', 'nome', 'responsavel'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['responsavel' => 'boolean'];
    }

    /** @return BelongsTo<Organizacao, $this> */
    public function organizacao(): BelongsTo
    {
        return $this->belongsTo(Organizacao::class);
    }
}
