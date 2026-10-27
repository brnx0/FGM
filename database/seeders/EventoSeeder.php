<?php

namespace Database\Seeders;

use App\Models\Evento;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class EventoSeeder extends Seeder
{
    public function run(): void
    {
        $evento = fn (string $titulo, string $resumo, string $data, ?string $local, bool $publicado = true) => [
            'titulo' => $titulo,
            'slug' => Str::slug($titulo),
            'resumo' => $resumo,
            'data_realizacao' => $data,
            'local' => $local,
            'imagem' => null,
            'publicado' => $publicado,
        ];

        $eventos = [
            $evento('VII Conferência Municipal de Cultura', 'Encontro de artistas, gestores e sociedade civil para debater as políticas culturais da cidade.', '2026-09-27', 'Teatro Gregório de Mattos, Salvador/BA'),
            $evento('Capoeira Viva', 'Rodas, oficinas e encontros de mestres celebrando a capoeira como patrimônio de Salvador.', '2026-09-06', 'Espaço Cultural da Barroquinha, Salvador/BA'),
            $evento('Jornada do Patrimônio Cultural', 'Roteiros, oficinas e conversas sobre o patrimônio cultural de Salvador.', '2026-08-15', 'Centro Histórico, Salvador/BA'),
            $evento('Dois de Julho', 'Cortejo e programação cultural da Independência da Bahia pelas ruas da cidade.', '2026-07-02', 'Lapinha ao Campo Grande, Salvador/BA'),
            $evento('Sacola Literária', 'Distribuição de livros e rodas de leitura em bairros e equipamentos culturais.', '2026-04-23', 'Bibliotecas municipais, Salvador/BA'),
            $evento('Curto Circuito das Artes', 'Circulação de apresentações artísticas curtas por espaços da cidade.', '2025-11-08', 'Salvador/BA'),
            $evento('Kaê Erê — Ler Criança', 'Contação de histórias e oficinas de leitura para crianças da rede municipal.', '2025-10-12', 'Espaço Boca de Brasa Subúrbio 360, Salvador/BA'),
            $evento('Mostra Escolas Criativas', 'Mostra de trabalhos artísticos desenvolvidos por estudantes da rede municipal.', '2025-06-20', 'Salvador/BA'),
            $evento('Circuito #Reconectar', 'Circuito de ações culturais que reconecta a população aos equipamentos da FGM.', '2025-03-29', 'Salvador/BA'),
            // Fora da home: ainda vai acontecer / não publicado.
            $evento('Festival Boca de Brasa', 'Mostra das produções dos espaços Boca de Brasa da cidade.', now()->addMonth()->toDateString(), 'Espaço Boca de Brasa Cajazeiras, Salvador/BA'),
            $evento('Esqueça um Livro', 'Ação de leitura em pontos de ônibus e praças.', '2025-08-09', 'Salvador/BA', publicado: false),
        ];

        foreach ($eventos as $dados) {
            $registro = Evento::withTrashed()->firstOrNew(['slug' => $dados['slug']]);

            // Só preenche o que é novo: nunca sobrescreve o que a retaguarda editou.
            if (! $registro->exists) {
                $registro->fill($dados)->save();
            }
        }
    }
}
