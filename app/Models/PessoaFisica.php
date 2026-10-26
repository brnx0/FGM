<?php

namespace App\Models;

use App\Enums\IdentidadeGenero;
use App\Enums\RacaCor;
use App\Enums\Territorio;
use Database\Factories\PessoaFisicaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PessoaFisica extends Model
{
    /** @use HasFactory<PessoaFisicaFactory> */
    use HasFactory;

    /** Colunas com o caminho de um arquivo enviado, no disco local. */
    public const array ARQUIVOS = [
        'foto_perfil', 'documento_cpf', 'documento_rg', 'documento_passaporte',
        'documento_crnm', 'documento_outros', 'comprovante_residencia',
    ];

    protected $table = 'pessoas_fisicas';

    protected $fillable = [
        'nome_social', 'nome_artistico', 'foto_perfil',
        'pais_origem', 'rg', 'documento_cpf', 'documento_rg', 'documento_passaporte', 'documento_crnm', 'documento_outros',
        'drt', 'cultura_principal_renda', 'ja_foi_avaliador', 'interesse_avaliador', 'representa_coletivo',
        'telefone', 'telefone_adicional', 'email',
        'identidade_genero', 'raca_cor', 'pcd',
        'cep', 'logradouro', 'numero', 'bairro', 'complemento', 'cidade', 'estado', 'territorio', 'comprovante_residencia',
    ];

    protected function casts(): array
    {
        return [
            'cultura_principal_renda' => 'boolean',
            'ja_foi_avaliador' => 'boolean',
            'interesse_avaliador' => 'boolean',
            'representa_coletivo' => 'boolean',
            'pcd' => 'boolean',
            'identidade_genero' => IdentidadeGenero::class,
            'raca_cor' => RacaCor::class,
            'territorio' => Territorio::class,
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
