<?php

namespace App\Models;

use App\Enums\Escolaridade;
use App\Enums\FaixaDeRenda;
use App\Enums\IdentidadeGenero;
use App\Enums\PovoTradicional;
use App\Enums\RacaCor;
use App\Enums\TipoDeficiencia;
use BackedEnum;
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

    /** Campos do perfil que o questionário do edital reaproveita. */
    public const array CAMPOS_DO_PERFIL = [
        'identidade_genero', 'raca_cor', 'pcd', 'deficiencia', 'deficiencia_outra',
        'escolaridade', 'renda', 'povo_tradicional', 'anos_atuacao',
    ];

    protected $table = 'pessoas_fisicas';

    protected $fillable = [
        'nome_social', 'nome_artistico', 'foto_perfil',
        'pais_origem', 'rg', 'documento_cpf', 'documento_rg', 'documento_passaporte', 'documento_crnm', 'documento_outros',
        'drt', 'cultura_principal_renda', 'ja_foi_avaliador', 'interesse_avaliador', 'representa_coletivo',
        'telefone', 'telefone_adicional', 'email',
        'identidade_genero', 'raca_cor', 'pcd', 'deficiencia', 'deficiencia_outra', 'escolaridade', 'renda', 'povo_tradicional', 'anos_atuacao',
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
            'deficiencia' => TipoDeficiencia::class,
            'escolaridade' => Escolaridade::class,
            'renda' => FaixaDeRenda::class,
            'povo_tradicional' => PovoTradicional::class,
            'anos_atuacao' => 'integer',
        ];
    }

    /** Cadastros anteriores ao perfil ficam incompletos até a pessoa preencher o que falta. */
    public function perfilCompleto(): bool
    {
        $basico = $this->identidade_genero !== null
            && $this->raca_cor !== null
            && $this->escolaridade !== null
            && $this->renda !== null
            && $this->povo_tradicional !== null
            && $this->anos_atuacao !== null;

        if (! $basico) {
            return false;
        }

        if (! $this->pcd) {
            return true;
        }

        return $this->deficiencia !== null
            && ($this->deficiencia !== TipoDeficiencia::Outra || filled($this->deficiencia_outra));
    }

    /**
     * Os valores do perfil como ficam gravados na inscrição (enums como texto).
     *
     * @return array<string, string|int|bool|null>
     */
    public function retratoDoPerfil(): array
    {
        return collect(self::CAMPOS_DO_PERFIL)
            ->mapWithKeys(function (string $campo): array {
                $valor = $this->{$campo};

                return [$campo => $valor instanceof BackedEnum ? $valor->value : $valor];
            })
            ->all();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
