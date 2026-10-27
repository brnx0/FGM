<?php

namespace App\Models;

use App\Enums\PorteOrganizacao;
use App\Models\Concerns\UsaDiaCivil;
use App\Support\Cnpj;
use Database\Factories\OrganizacaoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Organizacao extends Model
{
    /** @use HasFactory<OrganizacaoFactory> */
    use HasFactory;

    use UsaDiaCivil;

    public const int LIMITE_REPRESENTANTES = 10;

    public const int LIMITE_VINCULOS = 20;

    protected $table = 'organizacoes';

    protected $fillable = [
        'cnpj', 'razao_social', 'nome_fantasia', 'natureza_juridica', 'mei', 'finalidade_lucrativa',
        'municipio_ibge', 'inscricao_municipal', 'inscricao_estadual', 'porte', 'data_fundacao',
        'telefone', 'logo',
        'cep', 'logradouro', 'numero', 'sem_numero', 'complemento', 'bairro', 'cidade', 'uf',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'mei' => 'boolean',
            'finalidade_lucrativa' => 'boolean',
            'sem_numero' => 'boolean',
            'data_fundacao' => 'date',
            'porte' => PorteOrganizacao::class,
        ];
    }

    /** @return Attribute<string, string> */
    protected function cnpj(): Attribute
    {
        return Attribute::set(fn (string $valor): string => Cnpj::normalizar($valor));
    }

    /** @return Attribute<mixed, mixed> */
    protected function dataFundacao(): Attribute
    {
        return Attribute::set(fn ($valor) => self::diaParaGravar($valor));
    }

    /** @return HasMany<RepresentanteLegal, $this> */
    public function representantes(): HasMany
    {
        return $this->hasMany(RepresentanteLegal::class)->orderBy('ordem');
    }

    /** @return HasMany<VinculoOrganizacao, $this> */
    public function vinculos(): HasMany
    {
        return $this->hasMany(VinculoOrganizacao::class);
    }

    /** Organizações em que o CPF da pessoa tem vínculo (vale mesmo antes de o usuário existir). */
    public function scopeDaPessoa(Builder $query, User $user): void
    {
        $query->whereHas('vinculos', fn (Builder $vinculos) => $vinculos->where('cpf', $user->cpf));
    }

    public function temVinculo(User $user): bool
    {
        return $this->vinculos()->where('cpf', $user->cpf)->exists();
    }

    public function ehResponsavel(User $user): bool
    {
        return $this->vinculos()->where('cpf', $user->cpf)->where('responsavel', true)->exists();
    }

    public function logoUrl(): ?string
    {
        return $this->logo ? Storage::disk('public')->url($this->logo) : null;
    }
}
