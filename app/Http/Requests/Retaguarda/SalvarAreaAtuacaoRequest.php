<?php

namespace App\Http\Requests\Retaguarda;

use App\Models\AreaAtuacao;
use App\Rules\TamanhoEmBytes;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Criação e edição de área de atuação. O nome é único sem diferença de maiúsculas (no Oracle a comparação diferencia).
 */
class SalvarAreaAtuacaoRequest extends FormRequest
{
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
            'nome' => ['required', 'string', 'max:100', new TamanhoEmBytes(100), function (string $atributo, mixed $valor, Closure $falhar): void {
                $repetido = AreaAtuacao::query()
                    ->whereRaw('LOWER(nome) = ?', [mb_strtolower((string) $valor)])
                    ->when($this->area() !== null, fn ($q) => $q->whereKeyNot($this->area()->id))
                    ->exists();

                if ($repetido) {
                    $falhar('Já existe uma área de atuação com este nome.');
                }
            }],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nome' => 'nome',
        ];
    }

    private function area(): ?AreaAtuacao
    {
        $area = $this->route('area');

        return $area instanceof AreaAtuacao ? $area : null;
    }
}
