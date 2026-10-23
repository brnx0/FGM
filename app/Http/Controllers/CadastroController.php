<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class CadastroController extends Controller
{
    public function completar(): Response
    {
        return Inertia::render('Cadastro/Completar');
    }
}
