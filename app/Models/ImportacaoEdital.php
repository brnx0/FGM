<?php

namespace App\Models;

use App\Enums\ProvedorIa;
use App\Enums\SituacaoImportacao;
use Carbon\CarbonInterface;
use Database\Factories\ImportacaoEditalFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Importação de um edital a partir do PDF, analisado pela IA em segundo plano (comando editais:analisar).
 *
 * resposta: JSON recebido da IA; confiabilidade: {geral: ?int, blocos: {bloco: {nota: int, justificativa: string}}};
 * atencao: list<{bloco, tipo: 'nao_encontrado'|'divergencia'|'incerta', texto, itens: list<string>}>.
 * provedor e modelo: os da Integração com IA que responderam (ou, antes da resposta, os previstos).
 * execucao: identifica a análise em curso; só o processo lançado com ela pode concluir ou dar erro (ver editais:analisar).
 */
class ImportacaoEdital extends Model
{
    /** @use HasFactory<ImportacaoEditalFactory> */
    use HasFactory;

    /** Disco e pasta dos PDFs enviados. */
    public const string DISCO = 'local';

    public const string PASTA = 'editais/importacoes';

    /** Depois disso, uma análise que não terminou é dada como perdida (o processo morreu). */
    public const int MINUTOS_LIMITE = 15;

    public const string ERRO_SEM_TERMINO = 'A análise não terminou. Tente de novo.';

    protected $table = 'importacoes_edital';

    protected $fillable = [
        'user_id', 'edital_id', 'situacao', 'pdf', 'pdf_nome', 'provedor', 'modelo',
        'tokens_entrada', 'tokens_saida', 'tokens_total', 'erro', 'resposta', 'confiabilidade', 'atencao',
        'conferida_em', 'iniciada_em', 'execucao',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'situacao' => SituacaoImportacao::class,
            'provedor' => ProvedorIa::class,
            'tokens_entrada' => 'integer',
            'tokens_saida' => 'integer',
            'tokens_total' => 'integer',
            'resposta' => 'array',
            'confiabilidade' => 'array',
            'atencao' => 'array',
            'conferida_em' => 'datetime',
            'iniciada_em' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Edital, $this> */
    public function edital(): BelongsTo
    {
        return $this->belongsTo(Edital::class);
    }

    /** A situação para a tela: "analisando" há mais de 15 minutos é mostrada como erro. */
    public function situacaoExibida(?CarbonInterface $agora = null): SituacaoImportacao
    {
        return $this->semTermino($agora) ? SituacaoImportacao::Erro : $this->situacao;
    }

    /** O erro para a tela (o gravado ou o de análise sem término). */
    public function erroExibido(?CarbonInterface $agora = null): ?string
    {
        return $this->semTermino($agora) ? self::ERRO_SEM_TERMINO : $this->erro;
    }

    /** Identificador para uma nova análise. */
    public static function novaExecucao(): string
    {
        return (string) Str::uuid();
    }

    /**
     * Abre uma nova análise no mesmo registro se ele estiver com erro (gravado ou por análise sem término), numa só
     * atualização condicional: de duas tentativas ao mesmo tempo, só uma consegue.
     */
    public function abrirNovaTentativa(ProvedorIa $provedor, string $modelo, ?CarbonInterface $agora = null): bool
    {
        $agora ??= now();

        $abertas = static::query()
            ->whereKey($this->getKey())
            ->where(fn (Builder $consulta) => $consulta
                ->where('situacao', SituacaoImportacao::Erro->value)
                ->orWhere(fn (Builder $analisando) => $analisando
                    ->where('situacao', SituacaoImportacao::Analisando->value)
                    ->where('iniciada_em', '<', $agora->copy()->subMinutes(self::MINUTOS_LIMITE))))
            ->update([
                'situacao' => SituacaoImportacao::Analisando->value,
                'erro' => null,
                'provedor' => $provedor->value,
                'modelo' => $modelo,
                'iniciada_em' => $agora,
                'execucao' => self::novaExecucao(),
            ]);

        if ($abertas !== 1) {
            return false;
        }

        $this->refresh();

        return true;
    }

    /** Só a importação ainda na análise $execucao: analisando e sem edital. */
    public function scopeDaExecucao(Builder $consulta, string $execucao): void
    {
        $consulta->where('execucao', $execucao)
            ->where('situacao', SituacaoImportacao::Analisando->value)
            ->whereNull('edital_id');
    }

    private function semTermino(?CarbonInterface $agora): bool
    {
        return $this->situacao === SituacaoImportacao::Analisando
            && $this->iniciada_em->lt(($agora ?? now())->copy()->subMinutes(self::MINUTOS_LIMITE));
    }
}
