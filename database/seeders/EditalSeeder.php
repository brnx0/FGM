<?php

namespace Database\Seeders;

use App\Models\Edital;
use Illuminate\Database\Seeder;

class EditalSeeder extends Seeder
{
    public function run(): void
    {
        $hoje = now()->startOfDay();

        $editais = [
            [
                'titulo' => 'PNAB Salvador — Ciclo II',
                'slug' => 'pnab-salvador-ciclo-ii',
                'resumo' => 'Fomento a projetos culturais de Salvador com recursos da Política Nacional Aldir Blanc.',
                'categoria' => 'Fomento',
                'inscricoes_inicio' => $hoje->copy()->subDays(10)->toDateString(),
                'inscricoes_fim' => $hoje->copy()->addDays(20)->toDateString(),
            ],
            [
                'titulo' => 'SalCine 2',
                'slug' => 'salcine-2',
                'resumo' => 'Apoio à produção audiovisual soteropolitana, de curtas a séries.',
                'categoria' => 'Audiovisual',
                'inscricoes_inicio' => $hoje->copy()->subDays(5)->toDateString(),
                'inscricoes_fim' => $hoje->copy()->addDays(30)->toDateString(),
            ],
            [
                'titulo' => 'Edital Pontos de Cultura',
                'slug' => 'edital-pontos-de-cultura',
                'resumo' => 'Reconhecimento e apoio a iniciativas culturais da rede municipal.',
                'categoria' => 'Rede municipal',
                'inscricoes_inicio' => $hoje->copy()->addDays(15)->toDateString(),
                'inscricoes_fim' => $hoje->copy()->addDays(60)->toDateString(),
            ],
            [
                'titulo' => 'Lei Paulo Gustavo',
                'slug' => 'lei-paulo-gustavo',
                'resumo' => 'Fomento ao setor cultural com recursos da Lei Complementar nº 195/2022.',
                'categoria' => 'Fomento',
                'inscricoes_inicio' => $hoje->copy()->subDays(90)->toDateString(),
                'inscricoes_fim' => $hoje->copy()->subDays(30)->toDateString(),
            ],
        ];

        foreach ($editais as $dados) {
            Edital::updateOrCreate(
                ['slug' => $dados['slug']],
                $dados + ['imagem' => null, 'publicado' => true],
            );
        }
    }
}
