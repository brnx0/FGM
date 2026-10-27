<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** Troca de um arquivo guardado em disco: grava o novo e apaga o anterior. */
class ArquivoArmazenado
{
    /** Grava $novo em $pasta no disco $disco, apaga $atual (se houver) e devolve o caminho do novo. */
    public static function substituir(UploadedFile $novo, ?string $atual, string $disco, string $pasta): string
    {
        $caminho = $novo->store($pasta, $disco) ?: throw new RuntimeException("Não foi possível gravar o arquivo em {$disco}:{$pasta}.");

        if (filled($atual)) {
            Storage::disk($disco)->delete($atual);
        }

        return $caminho;
    }
}
