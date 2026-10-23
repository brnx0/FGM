<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/** Páginas institucionais de conteúdo fixo (texto provisório, a validar com a FGM). */
class PaginaController extends Controller
{
    public function aFgm(): Response
    {
        return Inertia::render('Institucional/AFgm');
    }

    public function duvidas(): Response
    {
        return Inertia::render('Institucional/Duvidas');
    }

    public function privacidade(): Response
    {
        return Inertia::render('Institucional/Privacidade');
    }

    public function termos(): Response
    {
        return Inertia::render('Institucional/Termos');
    }
}
