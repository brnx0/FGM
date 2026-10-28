<?php

namespace App\Http\Requests\Retaguarda;

use App\Models\Bairro;
use App\Models\PrefeituraBairro;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Criação e edição de Prefeitura-Bairro com a lista completa dos seus bairros. Nome da Prefeitura-Bairro e do bairro
 * são únicos sem diferença de maiúsculas (no Oracle a comparação diferencia), e o bairro é de uma Prefeitura-Bairro só.
 */
class SalvarPrefeituraBairroRequest extends FormRequest
{
    /** @var array<string, string>|null nome do bairro em minúsculas => nome da Prefeitura-Bairro dona */
    private ?array $bairrosDeOutras = null;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'numero' => ['required', 'integer', 'between:1,99', Rule::unique('prefeituras_bairro', 'numero')->ignore($this->prefeitura())],
            'nome' => ['required', 'string', 'max:60', function (string $atributo, mixed $valor, Closure $falhar): void {
                $repetido = PrefeituraBairro::query()
                    ->whereRaw('LOWER(nome) = ?', [mb_strtolower((string) $valor)])
                    ->when($this->prefeitura() !== null, fn ($q) => $q->whereKeyNot($this->prefeitura()->id))
                    ->exists();

                if ($repetido) {
                    $falhar('Já existe uma Prefeitura-Bairro com este nome.');
                }
            }],
            'bairros' => ['nullable', 'array', 'max:100'],
            'bairros.*.id' => ['nullable', 'integer'],
            'bairros.*.nome' => ['required', 'string', 'max:80', 'distinct:ignore_case', function (string $atributo, mixed $valor, Closure $falhar): void {
                $dona = $this->bairrosDeOutrasPrefeituras()[mb_strtolower((string) $valor)] ?? null;

                if ($dona !== null) {
                    $falhar("Este bairro já pertence à Prefeitura-Bairro {$dona}.");
                }
            }],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'numero.unique' => 'Já existe uma Prefeitura-Bairro com este número.',
            'numero.between' => 'Informe um número de 1 a 99.',
            'bairros.max' => 'Uma Prefeitura-Bairro pode ter até 100 bairros.',
            'bairros.*.nome.required' => 'Informe o nome do bairro ou remova a linha.',
            'bairros.*.nome.distinct' => 'Este bairro está repetido na lista.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'numero' => 'número',
            'nome' => 'nome',
            'bairros.*.nome' => 'bairro',
        ];
    }

    /**
     * Bairros na ordem enviada; id vazio é bairro novo.
     *
     * @return list<array{id: ?int, nome: string}>
     */
    public function bairrosParaSalvar(): array
    {
        // A validação monta os itens na ordem das regras: a posição enviada manda.
        $validados = (array) $this->validated('bairros', []);
        ksort($validados, SORT_NUMERIC);

        return array_values(array_map(fn (array $bairro): array => [
            'id' => filled($bairro['id'] ?? null) ? (int) $bairro['id'] : null,
            'nome' => $bairro['nome'],
        ], $validados));
    }

    private function prefeitura(): ?PrefeituraBairro
    {
        $prefeitura = $this->route('prefeitura');

        return $prefeitura instanceof PrefeituraBairro ? $prefeitura : null;
    }

    /** @return array<string, string> */
    private function bairrosDeOutrasPrefeituras(): array
    {
        return $this->bairrosDeOutras ??= Bairro::query()
            ->with('prefeituraBairro:id,nome')
            ->when($this->prefeitura() !== null, fn ($q) => $q->where('prefeitura_bairro_id', '!=', $this->prefeitura()->id))
            ->get(['id', 'nome', 'prefeitura_bairro_id'])
            ->mapWithKeys(fn (Bairro $bairro): array => [mb_strtolower($bairro->nome) => $bairro->prefeituraBairro->nome])
            ->all();
    }
}
