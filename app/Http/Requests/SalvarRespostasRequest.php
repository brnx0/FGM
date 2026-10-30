<?php

namespace App\Http\Requests;

use App\Enums\TipoPergunta;
use App\Models\EditalPergunta;
use App\Models\EditalQuestionarioSecao;
use App\Models\Inscricao;
use App\Rules\TamanhoEmBytes;
use App\Support\Inscricao\QuestionarioDaInscricao;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Respostas de uma seção do questionário, só do dono da inscrição. Só as perguntas da seção que valem para a inscrição
 * são validadas (e gravadas); outras chaves são ignoradas. O que falta responder, o período e a situação ficam no
 * controller.
 */
class SalvarRespostasRequest extends FormRequest
{
    public const string ERRO_LIMITE = 'Use no máximo :max caracteres.';

    public const string ERRO_DATA = 'Digite uma data válida.';

    public const string ERRO_NUMERO = 'Informe um número.';

    public const string ERRO_OPCAO = 'Escolha uma opção da lista.';

    /** @var Collection<int, EditalPergunta>|null */
    private ?Collection $perguntas = null;

    public function authorize(): bool
    {
        Gate::authorize('gerenciar', $this->inscricao());

        return true;
    }

    /** Quebras de linha "\r\n" contam como uma só no limite de caracteres. */
    protected function prepareForValidation(): void
    {
        $respostas = $this->input('respostas');

        if (! is_array($respostas)) {
            return;
        }

        foreach ($respostas as $id => $resposta) {
            if (! is_array($resposta)) {
                continue;
            }

            foreach (['texto', 'outro'] as $campo) {
                if (is_string($resposta[$campo] ?? null)) {
                    $respostas[$id][$campo] = str_replace("\r\n", "\n", $resposta[$campo]);
                }
            }
        }

        $this->merge(['respostas' => $respostas]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $regras = ['respostas' => ['nullable', 'array']];

        foreach ($this->perguntas() as $pergunta) {
            $chave = 'respostas.'.$pergunta->id;
            $regras[$chave] = ['nullable', 'array'];

            if ($pergunta->tipo->temOpcoes() || $pergunta->tipo === TipoPergunta::SimNao || $pergunta->tipo === TipoPergunta::Declaracao) {
                $regras["{$chave}.opcoes"] = $pergunta->tipo === TipoPergunta::MultiplaEscolha
                    ? ['nullable', 'array']
                    : ['nullable', 'array', 'max:1'];
                $regras["{$chave}.opcoes.*"] = ['string', Rule::in($pergunta->chavesDasOpcoes())];
                $regras["{$chave}.outro"] = ['nullable', 'string', 'max:1000', new TamanhoEmBytes(1000)];

                continue;
            }

            $regras["{$chave}.texto"] = match ($pergunta->tipo) {
                TipoPergunta::Data => ['nullable', 'string', 'date_format:Y-m-d'],
                TipoPergunta::Numero => ['nullable', 'numeric', 'between:-999999999999,999999999999'],
                default => ['nullable', 'string', 'max:'.$pergunta->limiteDeCaracteres()],
            };
        }

        return $regras;
    }

    /**
     * O app não tem tradução do Laravel.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $mensagens = ['respostas.array' => QuestionarioDaInscricao::ERRO_RESPONDA];

        foreach ($this->perguntas() as $pergunta) {
            $chave = 'respostas.'.$pergunta->id;
            $mensagens["{$chave}.array"] = QuestionarioDaInscricao::ERRO_RESPONDA;

            foreach (['opcoes.array', 'opcoes.max', 'opcoes.*.string', 'opcoes.*.in'] as $regra) {
                $mensagens["{$chave}.{$regra}"] = self::ERRO_OPCAO;
            }
            $mensagens["{$chave}.outro.string"] = QuestionarioDaInscricao::ERRO_OUTRO;
            $mensagens["{$chave}.outro.max"] = self::ERRO_LIMITE;

            foreach (match ($pergunta->tipo) {
                TipoPergunta::Data => ['string' => self::ERRO_DATA, 'date_format' => self::ERRO_DATA],
                TipoPergunta::Numero => ['numeric' => self::ERRO_NUMERO, 'between' => self::ERRO_NUMERO],
                default => ['string' => QuestionarioDaInscricao::ERRO_RESPONDA, 'max' => self::ERRO_LIMITE],
            } as $regra => $mensagem) {
                $mensagens["{$chave}.texto.{$regra}"] = $mensagem;
            }
        }

        return $mensagens;
    }

    /**
     * As perguntas da seção que valem para a inscrição, na ordem.
     *
     * @return Collection<int, EditalPergunta>
     */
    public function perguntas(): Collection
    {
        $secao = $this->route('secao');
        $secaoId = $secao instanceof EditalQuestionarioSecao ? (int) $secao->id : null;

        return $this->perguntas ??= (new QuestionarioDaInscricao($this->inscricao()))->perguntasQueValem()
            ->filter(fn (EditalPergunta $pergunta): bool => (int) $pergunta->edital_questionario_secao_id === $secaoId)
            ->values();
    }

    private function inscricao(): Inscricao
    {
        return $this->route('inscricao');
    }
}
