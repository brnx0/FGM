<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/** Arquivos de um registro guardados em disco, sem deixar o registro apontando para um arquivo apagado. */
class ArquivoArmazenado
{
    /** Grava $arquivo em $pasta no disco $disco e devolve o caminho. */
    public static function guardar(UploadedFile $arquivo, string $disco, string $pasta): string
    {
        return $arquivo->store($pasta, $disco) ?: throw new RuntimeException("Não foi possível gravar o arquivo em {$disco}:{$pasta}.");
    }

    public static function apagar(string $disco, ?string $caminho): void
    {
        if (filled($caminho)) {
            Storage::disk($disco)->delete($caminho);
        }
    }

    /**
     * Salva o modelo e só então apaga os arquivos substituídos ($antigos).
     * Se o salvamento falhar, apaga os recém-gravados ($novos) e os antigos ficam como estavam.
     *
     * @param  list<array{string, ?string}>  $novos  Pares [disco, caminho] já gravados.
     * @param  list<array{string, ?string}>  $antigos  Pares [disco, caminho] a apagar após salvar.
     */
    public static function salvarTrocando(Model $modelo, array $novos, array $antigos): void
    {
        try {
            $salvou = $modelo->save();
        } catch (Throwable $erro) {
            self::apagarTodos($novos);

            throw $erro;
        }

        if (! $salvou) {
            self::apagarTodos($novos);

            throw new RuntimeException('Não foi possível salvar o registro.');
        }

        self::apagarTodos($antigos);
    }

    /**
     * @param  list<array{string, ?string}>  $arquivos
     */
    private static function apagarTodos(array $arquivos): void
    {
        foreach ($arquivos as [$disco, $caminho]) {
            self::apagar($disco, $caminho);
        }
    }
}
