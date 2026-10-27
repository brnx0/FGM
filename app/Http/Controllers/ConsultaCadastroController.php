<?php

namespace App\Http\Controllers;

use App\Models\Organizacao;
use App\Support\Cnpj;
use App\Support\ConsultaCep;
use App\Support\ConsultaCnpj;
use App\Support\Municipios;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Consultas auxiliares do formulário de cadastro (JSON, chamadas pelo front). */
class ConsultaCadastroController extends Controller
{
    public function cnpj(string $cnpj): JsonResponse
    {
        $cnpj = Cnpj::normalizar($cnpj);

        if (! Cnpj::valido($cnpj)) {
            return response()->json(['message' => 'CNPJ inválido.'], 422);
        }

        if (Organizacao::where('cnpj', $cnpj)->exists()) {
            return response()->json(['jaCadastrado' => true]);
        }

        $dados = ConsultaCnpj::buscar($cnpj);

        return response()->json($dados === null ? ['encontrado' => false] : ['encontrado' => true, 'dados' => $dados]);
    }

    public function cep(string $cep): JsonResponse
    {
        if (preg_match('/^\d{8}$/', $cep) !== 1) {
            return response()->json(['message' => 'CEP inválido.'], 422);
        }

        $dados = ConsultaCep::buscar($cep);

        return response()->json($dados === null ? ['encontrado' => false] : ['encontrado' => true, 'dados' => $dados]);
    }

    public function municipios(Request $request): JsonResponse
    {
        $busca = trim((string) $request->query('busca', ''));

        return response()->json(mb_strlen($busca) < 2 ? [] : Municipios::buscar($busca));
    }
}
