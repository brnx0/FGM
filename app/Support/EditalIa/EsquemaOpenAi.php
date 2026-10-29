<?php

namespace App\Support\EditalIa;

/**
 * Traduz o esquema da resposta (EsquemaDaResposta, no formato do Gemini) para o JSON Schema da saída estruturada da
 * OpenAI em modo strict: tipos em minúsculas; anulável vira união com null (no tipo e, havendo, no enum); todo objeto
 * com todas as propriedades em required e additionalProperties: false. Só o subconjunto que o esquema usa.
 */
final class EsquemaOpenAi
{
    /**
     * @param  array<string, mixed>  $esquemaGemini
     * @return array<string, mixed>
     */
    public static function de(array $esquemaGemini): array
    {
        $anulavel = ($esquemaGemini['nullable'] ?? false) === true;
        $tipo = strtolower((string) ($esquemaGemini['type'] ?? ''));
        $esquema = ['type' => $anulavel ? [$tipo, 'null'] : $tipo];

        if (isset($esquemaGemini['description'])) {
            $esquema['description'] = $esquemaGemini['description'];
        }

        if (isset($esquemaGemini['enum'])) {
            $esquema['enum'] = $anulavel ? [...$esquemaGemini['enum'], null] : $esquemaGemini['enum'];
        }

        if (isset($esquemaGemini['items'])) {
            $esquema['items'] = self::de($esquemaGemini['items']);
        }

        if ($tipo === 'object') {
            $propriedades = $esquemaGemini['properties'] ?? [];
            $esquema['properties'] = array_map(fn (array $propriedade): array => self::de($propriedade), $propriedades);
            $esquema['required'] = array_keys($propriedades);
            $esquema['additionalProperties'] = false;
        }

        return $esquema;
    }
}
