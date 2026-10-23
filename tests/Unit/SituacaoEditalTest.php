<?php

namespace Tests\Unit;

use App\Enums\SituacaoEdital;
use App\Models\Edital;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SituacaoEditalTest extends TestCase
{
    #[DataProvider('casos')]
    public function test_situacao_por_datas(string $agora, SituacaoEdital $esperada): void
    {
        $edital = new Edital([
            'inscricoes_inicio' => '2026-11-01',
            'inscricoes_fim' => '2026-11-15',
        ]);

        $this->assertSame($esperada, $edital->situacao(Carbon::parse($agora, 'America/Bahia')));
    }

    public static function casos(): array
    {
        return [
            'antes do início' => ['2026-10-31 10:00', SituacaoEdital::EmBreve],
            'primeiro dia' => ['2026-11-01 00:00', SituacaoEdital::Aberto],
            'último dia 23:30' => ['2026-11-15 23:30', SituacaoEdital::Aberto],
            'dia seguinte' => ['2026-11-16 00:00', SituacaoEdital::Encerrado],
        ];
    }

    public function test_rotulos(): void
    {
        $this->assertSame('Em breve', SituacaoEdital::EmBreve->rotulo());
        $this->assertSame('Aberto', SituacaoEdital::Aberto->rotulo());
        $this->assertSame('Encerrado', SituacaoEdital::Encerrado->rotulo());
    }
}
