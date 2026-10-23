<?php

namespace Database\Seeders;

use App\Models\Evento;
use Illuminate\Database\Seeder;

class EventoSeeder extends Seeder
{
    public function run(): void
    {
        $eventos = [
            [
                'titulo' => 'Jornada do Patrimônio Cultural',
                'slug' => 'jornada-do-patrimonio-cultural',
                'resumo' => 'Roteiros, oficinas e conversas sobre o patrimônio cultural de Salvador.',
                'data_realizacao' => '2026-08-15',
                'local' => 'Centro Histórico, Salvador/BA',
            ],
            [
                'titulo' => 'Curto Circuito das Artes',
                'slug' => 'curto-circuito-das-artes',
                'resumo' => 'Circulação de apresentações artísticas curtas por espaços da cidade.',
                'data_realizacao' => '2025-11-08',
                'local' => 'Salvador/BA',
            ],
            [
                'titulo' => 'Mostra Escolas Criativas',
                'slug' => 'mostra-escolas-criativas',
                'resumo' => 'Mostra de trabalhos artísticos desenvolvidos por estudantes da rede municipal.',
                'data_realizacao' => '2025-06-20',
                'local' => 'Salvador/BA',
            ],
            [
                'titulo' => 'Circuito #Reconectar',
                'slug' => 'circuito-reconectar',
                'resumo' => 'Circuito de ações culturais que reconecta a população aos equipamentos da FGM.',
                'data_realizacao' => '2025-03-29',
                'local' => 'Salvador/BA',
            ],
        ];

        foreach ($eventos as $dados) {
            Evento::updateOrCreate(
                ['slug' => $dados['slug']],
                $dados + ['imagem' => null, 'publicado' => true],
            );
        }
    }
}
