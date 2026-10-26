<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Eventos de exemplo, em código, para a seção "Eventos" da home.
 * Não há tabela de eventos por enquanto: quando o painel do gestor existir,
 * esta lista dá lugar ao banco.
 *
 * @phpstan-type DadosEvento array{titulo: string, resumo: string, data_realizacao: string, local: ?string, imagem: ?string, publicado: bool}
 */
final class EventosMock
{
    /** @param list<DadosEvento>|null $eventos Outra lista (usada nos testes). */
    public function __construct(private ?array $eventos = null) {}

    /**
     * Publicados e já realizados (até hoje, em America/Bahia), do mais recente ao mais antigo.
     *
     * @return list<DadosEvento>
     */
    public function realizados(int $limite = 6, ?CarbonInterface $agora = null): array
    {
        $hoje = ($agora === null ? CarbonImmutable::now() : CarbonImmutable::instance($agora))
            ->setTimezone(config('app.timezone'))
            ->toDateString();

        $realizados = array_filter(
            $this->eventos ?? self::exemplos(),
            fn (array $e) => $e['publicado'] && $e['data_realizacao'] <= $hoje,
        );
        usort($realizados, fn (array $a, array $b) => strcmp($b['data_realizacao'], $a['data_realizacao']));

        return array_slice($realizados, 0, $limite);
    }

    /** @return list<DadosEvento> */
    private static function exemplos(): array
    {
        $evento = fn (string $titulo, string $resumo, string $data, ?string $local, bool $publicado = true) => [
            'titulo' => $titulo,
            'resumo' => $resumo,
            'data_realizacao' => $data,
            'local' => $local,
            'imagem' => null,
            'publicado' => $publicado,
        ];

        return [
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
            $evento('Festival Boca de Brasa', 'Mostra das produções dos espaços Boca de Brasa da cidade.', CarbonImmutable::now()->addMonth()->toDateString(), 'Espaço Boca de Brasa Cajazeiras, Salvador/BA'),
            $evento('Esqueça um Livro', 'Ação de leitura em pontos de ônibus e praças.', '2025-08-09', 'Salvador/BA', publicado: false),
        ];
    }
}
