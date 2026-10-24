<?php

namespace Tests\Unit;

use App\Support\EventosMock;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EventosMockTest extends TestCase
{
    public function test_realizados_so_publicados_e_passados_do_mais_recente(): void
    {
        $mock = new EventosMock([
            $this->evento('Agosto', '2026-08-10'),
            $this->evento('Hoje', '2026-11-15'),
            $this->evento('Futuro', '2026-12-10'),
            $this->evento('Oculto', '2026-01-10', publicado: false),
        ]);

        $titulos = array_column($mock->realizados(6, Carbon::parse('2026-11-15 23:30', 'America/Bahia')), 'titulo');

        $this->assertSame(['Hoje', 'Agosto'], $titulos);
    }

    public function test_hoje_e_calculado_no_fuso_de_salvador(): void
    {
        $mock = new EventosMock([$this->evento('Dia 15', '2026-11-15')]);

        // 01:30 UTC do dia 15 ainda é dia 14 em Salvador.
        $this->assertCount(0, $mock->realizados(6, Carbon::parse('2026-11-15 01:30', 'UTC')));
    }

    public function test_respeita_o_limite(): void
    {
        $eventos = array_map(fn (int $d) => $this->evento("E{$d}", sprintf('2025-01-%02d', $d)), range(1, 9));

        $this->assertCount(6, (new EventosMock($eventos))->realizados(6));
    }

    public function test_exemplos_tem_mais_de_seis_realizados(): void
    {
        $this->assertCount(6, (new EventosMock)->realizados(6));
    }

    /** @return array<string, mixed> */
    private function evento(string $titulo, string $data, bool $publicado = true): array
    {
        return [
            'titulo' => $titulo,
            'resumo' => '',
            'data_realizacao' => $data,
            'local' => null,
            'imagem' => null,
            'publicado' => $publicado,
        ];
    }
}
