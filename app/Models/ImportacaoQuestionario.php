<?php

namespace App\Models;

use App\Enums\ProvedorIa;
use App\Enums\SituacaoImportacao;
use Carbon\CarbonInterface;
use Database\Factories\ImportacaoQuestionarioFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Importação do questionário de um edital a partir do espelho do formulário (PDF ou Word .docx), analisado pela IA
 * em segundo plano (comando questionarios:analisar). Concluída, o questionário do edital foi substituído.
 *
 * resposta: JSON recebido da IA; confiabilidade: {geral: ?int, blocos: {questionario?: {nota: int, justificativa: string}}};
 * atencao: list<{bloco, tipo: 'nao_encontrado'|'divergencia'|'incerta', texto, itens: list<string>}>.
 * provedor e modelo: os da Integração com IA que responderam (ou, antes da resposta, os previstos).
 * substituir: quem enviou confirmou substituir o questionário existente.
 * execucao: identifica a análise em curso; só o processo lançado com ela pode concluir ou dar erro.
 */
class ImportacaoQuestionario extends Model
{
    /** @use HasFactory<ImportacaoQuestionarioFactory> */
    use HasFactory;

    /** Disco e pasta dos arquivos enviados. */
    public const string DISCO = 'local';

    public const string PASTA = 'editais/questionarios';

    protected $table = 'importacoes_questionario';

    protected $fillable = [
        'user_id', 'edital_id', 'situacao', 'arquivo', 'arquivo_nome', 'tipo_arquivo', 'substituir', 'provedor', 'modelo',
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
            'substituir' => 'boolean',
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
        return $this->semTermino($agora) ? ImportacaoEdital::ERRO_SEM_TERMINO : $this->erro;
    }

    /** Só a importação ainda na análise $execucao: analisando. */
    public function scopeDaExecucao(Builder $consulta, string $execucao): void
    {
        $consulta->where('execucao', $execucao)
            ->where('situacao', SituacaoImportacao::Analisando->value);
    }

    /** Análises ainda no prazo (as "analisando" de mais de 15 minutos são dadas como perdidas). */
    public function scopeEmAndamento(Builder $consulta, ?CarbonInterface $agora = null): void
    {
        $consulta->where('situacao', SituacaoImportacao::Analisando->value)
            ->where('iniciada_em', '>=', ($agora ?? now())->copy()->subMinutes(ImportacaoEdital::MINUTOS_LIMITE));
    }

    /** Análises dadas como perdidas: "analisando" há mais de 15 minutos. */
    public function scopeSemTermino(Builder $consulta, ?CarbonInterface $agora = null): void
    {
        $consulta->where('situacao', SituacaoImportacao::Analisando->value)
            ->where('iniciada_em', '<', ($agora ?? now())->copy()->subMinutes(ImportacaoEdital::MINUTOS_LIMITE));
    }

    private function semTermino(?CarbonInterface $agora): bool
    {
        return $this->situacao === SituacaoImportacao::Analisando
            && $this->iniciada_em->lt(($agora ?? now())->copy()->subMinutes(ImportacaoEdital::MINUTOS_LIMITE));
    }
}
