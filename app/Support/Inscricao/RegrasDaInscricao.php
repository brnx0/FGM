<?php

namespace App\Support\Inscricao;

use App\Enums\ModalidadeCota;
use App\Enums\NaturezaProponente;
use App\Models\Edital;
use App\Models\EditalCategoria;
use App\Models\EditalChamada;
use App\Models\Inscricao;
use App\Models\Organizacao;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Regras de quem pode se inscrever num edital e com o quê: cadastros do usuário, categorias, cotas,
 * chamada, limite de inscrições e escolha de cotas. As regras que dependem da inscrição ficam em Inscricao.
 */
final class RegrasDaInscricao
{
    public const string ERRO_COTA_NAO_OFERECIDA = 'Escolha só entre as cotas que o edital oferece.';

    public const string ERRO_NEGRO_E_INDIGENA = 'Escolha só uma entre as cotas para pessoas negras e indígenas.';

    /**
     * Cadastros com que o usuário pode se inscrever: ele mesmo e as organizações em que é responsável.
     * `aceito` diz se a natureza do cadastro é uma das que o edital aceita.
     *
     * @return list<array{tipo: 'pessoa_fisica'|'organizacao', id: ?int, nome: string, documento: string, natureza: NaturezaProponente, aceito: bool}>
     */
    public static function cadastros(User $user, Edital $edital): array
    {
        $cadastros = [[
            'tipo' => 'pessoa_fisica',
            'id' => null,
            'nome' => $user->name,
            'documento' => $user->cpf,
            'natureza' => NaturezaProponente::PessoaFisica,
            'aceito' => self::aceitaNatureza($edital->naturezas, NaturezaProponente::PessoaFisica),
        ]];

        $organizacoes = Organizacao::query()
            ->whereHas('vinculos', fn ($vinculos) => $vinculos->where('cpf', $user->cpf)->where('responsavel', true))
            ->get()
            ->map(function (Organizacao $organizacao) use ($edital): array {
                $natureza = NaturezaProponente::daOrganizacao($organizacao);

                return [
                    'tipo' => 'organizacao',
                    'id' => (int) $organizacao->id,
                    'nome' => $organizacao->nome_fantasia ?? $organizacao->razao_social,
                    'documento' => $organizacao->cnpj,
                    'natureza' => $natureza,
                    'aceito' => self::aceitaNatureza($edital->naturezas, $natureza),
                ];
            })
            ->sortBy('nome', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();

        return [...$cadastros, ...$organizacoes];
    }

    /**
     * Categorias do edital que a natureza pode escolher, na ordem do edital.
     *
     * @return Collection<int, EditalCategoria>
     */
    public static function categorias(Edital $edital, NaturezaProponente $natureza): Collection
    {
        return $edital->categorias()->get()
            ->filter(fn (EditalCategoria $categoria): bool => self::aceitaNatureza($categoria->naturezas, $natureza))
            ->values();
    }

    /**
     * Cotas que o edital oferece (marcadas como ativas).
     *
     * @return list<ModalidadeCota>
     */
    public static function cotasOferecidas(Edital $edital): array
    {
        return array_values(array_filter(
            ModalidadeCota::cases(),
            fn (ModalidadeCota $modalidade): bool => ($edital->cotas[$modalidade->value]['ativa'] ?? false) === true,
        ));
    }

    /**
     * Mensagem de erro das cotas escolhidas, ou nula quando valem.
     *
     * @param  list<ModalidadeCota>  $cotas
     */
    public static function erroNasCotas(Edital $edital, array $cotas): ?string
    {
        $oferecidas = self::cotasOferecidas($edital);
        foreach ($cotas as $cota) {
            if (! in_array($cota, $oferecidas, true)) {
                return self::ERRO_COTA_NAO_OFERECIDA;
            }
        }

        $vedado = ($edital->cotas['veda_negro_indigena'] ?? null) === true;
        if ($vedado && in_array(ModalidadeCota::Negros, $cotas, true) && in_array(ModalidadeCota::Indigenas, $cotas, true)) {
            return self::ERRO_NEGRO_E_INDIGENA;
        }

        return null;
    }

    /**
     * Chamada em que a inscrição fica: a da categoria; sem categoria ou sem chamada nela, a única do edital.
     */
    public static function chamada(Edital $edital, ?EditalCategoria $categoria): ?EditalChamada
    {
        if ($categoria?->edital_chamada_id !== null) {
            return $edital->chamadas()->whereKey($categoria->edital_chamada_id)->first();
        }

        $chamadas = $edital->chamadas()->get();

        return $chamadas->count() === 1 ? $chamadas->first() : null;
    }

    /**
     * O cadastro (CPF ou CNPJ só dígitos) já tem o máximo de inscrições, rascunhos e finalizadas, no edital
     * ou, com `limite_por_chamada`, na chamada. `$ignorar` é a inscrição que está sendo editada.
     */
    public static function limiteAtingido(Edital $edital, ?EditalChamada $chamada, string $documentoCadastro, ?int $ignorar = null): bool
    {
        $limite = $edital->inscricoes_por_proponente;
        if ($limite === null) {
            return false;
        }

        $inscricoes = Inscricao::query()
            ->where('edital_id', $edital->id)
            ->where('documento_cadastro', $documentoCadastro);

        if ($edital->limite_por_chamada && $chamada !== null) {
            $inscricoes->where('edital_chamada_id', $chamada->id);
        }
        if ($ignorar !== null) {
            $inscricoes->whereKeyNot($ignorar);
        }

        return $inscricoes->count() >= $limite;
    }

    /**
     * Lista de naturezas nula (ou vazia) vale para todas.
     *
     * @param  Collection<int, NaturezaProponente>|null  $naturezas
     */
    public static function aceitaNatureza(?Collection $naturezas, NaturezaProponente $natureza): bool
    {
        return $naturezas === null || $naturezas->isEmpty() || $naturezas->contains($natureza);
    }
}
