<?php

namespace App\Http\Requests;

use App\Enums\ModalidadeCota;
use App\Enums\NaturezaProponente;
use App\Models\Edital;
use App\Models\EditalCategoria;
use App\Models\Inscricao;
use App\Support\Inscricao\PropsDaInscricao;
use App\Support\Inscricao\RegrasDaInscricao;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use LogicException;

/**
 * Escolhas da inscrição: cadastro, categoria e cotas. Serve a criar (rota do edital) e a trocar as escolhas
 * do rascunho (rota da inscrição, só do dono). O limite de inscrições e o período ficam no controller.
 *
 * @phpstan-type CadastroDoUsuario array{tipo: 'pessoa_fisica'|'organizacao', id: ?int, nome: string, documento: string, natureza: NaturezaProponente, aceito: bool}
 */
class SalvarInscricaoRequest extends FormRequest
{
    public const string ERRO_CADASTRO = 'Escolha um dos seus cadastros.';

    public const string ERRO_NATUREZA_NAO_ACEITA = 'Este cadastro não pode se inscrever neste edital.';

    /** @var list<CadastroDoUsuario>|null */
    private ?array $cadastros = null;

    /** @var Collection<int, EditalCategoria>|null */
    private ?Collection $categorias = null;

    public function authorize(): bool
    {
        $inscricao = $this->route('inscricao');

        if ($inscricao instanceof Inscricao) {
            Gate::authorize('gerenciar', $inscricao);
        } else {
            abort_unless($this->edital()->estaVisivel(), 404);
        }

        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cadastro' => [
                'bail',
                'required',
                'string',
                function (string $atributo, mixed $valor, Closure $falhar): void {
                    $cadastro = $this->encontrarCadastro();

                    if ($cadastro === null) {
                        $falhar(self::ERRO_CADASTRO);
                    } elseif (! $cadastro['aceito']) {
                        $falhar(self::ERRO_NATUREZA_NAO_ACEITA);
                    }
                },
            ],
            'categoria' => [
                Rule::requiredIf(fn (): bool => $this->categoriasDoCadastro()->isNotEmpty()),
                'nullable',
                'integer',
                Rule::in($this->categoriasDoCadastro()->map(fn (EditalCategoria $categoria): string => (string) $categoria->id)->all()),
            ],
            'cotas' => [
                'bail',
                'nullable',
                'array',
                function (string $atributo, mixed $valor, Closure $falhar): void {
                    $cotas = array_map(fn (mixed $cota): ?ModalidadeCota => is_string($cota) ? ModalidadeCota::tryFrom($cota) : null, $valor);

                    $erro = in_array(null, $cotas, true)
                        ? RegrasDaInscricao::ERRO_COTA_NAO_OFERECIDA
                        : RegrasDaInscricao::erroNasCotas($this->edital(), array_values($cotas));

                    if ($erro !== null) {
                        $falhar($erro);
                    }
                },
            ],
        ];
    }

    /**
     * O app não tem tradução do Laravel.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cadastro.required' => 'Escolha com qual cadastro você vai se inscrever.',
            'cadastro.string' => self::ERRO_CADASTRO,
            'categoria.required' => 'Escolha a categoria.',
            'categoria.integer' => 'Escolha uma das categorias disponíveis para este cadastro.',
            'categoria.in' => 'Escolha uma das categorias disponíveis para este cadastro.',
            'cotas.array' => 'Escolha as cotas na lista.',
        ];
    }

    /** O edital da inscrição: o da URL ao criar, o da própria inscrição ao trocar as escolhas. */
    public function edital(): Edital
    {
        $inscricao = $this->route('inscricao');

        return $inscricao instanceof Inscricao ? $inscricao->edital : $this->route('edital');
    }

    /**
     * O cadastro escolhido; só depois de validar.
     *
     * @return CadastroDoUsuario
     */
    public function cadastroEscolhido(): array
    {
        return $this->encontrarCadastro() ?? throw new LogicException('Cadastro não validado.');
    }

    /** A categoria escolhida; nula quando a natureza do cadastro não tem categorias no edital. Só depois de validar. */
    public function categoriaEscolhida(): ?EditalCategoria
    {
        $id = $this->validated('categoria');

        return $id === null ? null : $this->categoriasDoCadastro()->first(fn (EditalCategoria $categoria): bool => (int) $categoria->id === (int) $id);
    }

    /**
     * As cotas marcadas, sem repetição. Só depois de validar.
     *
     * @return list<ModalidadeCota>
     */
    public function cotasEscolhidas(): array
    {
        return array_map(ModalidadeCota::from(...), array_values(array_unique($this->validated('cotas') ?? [])));
    }

    /** @return CadastroDoUsuario|null */
    private function encontrarCadastro(): ?array
    {
        $chave = $this->input('cadastro');
        $this->cadastros ??= RegrasDaInscricao::cadastros($this->user(), $this->edital());

        foreach ($this->cadastros as $cadastro) {
            if (is_string($chave) && PropsDaInscricao::chaveDoCadastro($cadastro['id']) === $chave) {
                return $cadastro;
            }
        }

        return null;
    }

    /**
     * Categorias do edital que a natureza do cadastro escolhido pode escolher; vazia sem cadastro válido.
     *
     * @return Collection<int, EditalCategoria>
     */
    private function categoriasDoCadastro(): Collection
    {
        $cadastro = $this->encontrarCadastro();

        return $this->categorias ??= $cadastro === null
            ? new Collection
            : RegrasDaInscricao::categorias($this->edital(), $cadastro['natureza']);
    }
}
