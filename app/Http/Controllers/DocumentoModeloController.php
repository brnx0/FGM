<?php

namespace App\Http\Controllers;

use App\Models\Edital;
use App\Models\EditalDocumento;
use App\Support\Edital\SincronizadorDoEdital;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Arquivo-modelo de um documento exigido, baixado no portal só de edital visível. */
class DocumentoModeloController extends Controller
{
    public function __invoke(Edital $edital, EditalDocumento $documento): StreamedResponse
    {
        $disco = Storage::disk(SincronizadorDoEdital::DISCO_MODELOS);

        abort_unless($edital->estaVisivel() && filled($documento->modelo) && $disco->exists($documento->modelo), 404);

        return $disco->download($documento->modelo, $documento->modelo_nome ?: basename($documento->modelo));
    }
}
