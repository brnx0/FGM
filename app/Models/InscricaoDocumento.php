<?php

namespace App\Models;

use App\Enums\ProvedorIa;
use App\Enums\SituacaoConferencia;
use Carbon\CarbonInterface;
use Database\Factories\InscricaoDocumentoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Arquivo enviado para um documento exigido na inscrição, com o resultado da conferência por IA.
 *
 * execucao: identifica a conferência em curso; só o processo lançado com ela pode concluí-la.
 * provedor e modelo: os da Integração com IA que conferiram (ou, antes da resposta, os previstos).
 */
class InscricaoDocumento extends Model
{
    /** @use HasFactory<InscricaoDocumentoFactory> */
    use HasFactory;

    /** Depois disso, uma conferência que não terminou é mostrada como não conferida (o processo morreu). */
    public const int MINUTOS_LIMITE = 5;

    protected $table = 'inscricao_documentos';

    protected $fillable = [
        'inscricao_id', 'edital_documento_id', 'arquivo', 'arquivo_nome', 'situacao', 'motivo', 'erro',
        'provedor', 'modelo', 'execucao', 'iniciada_em', 'conferido_em',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'situacao' => SituacaoConferencia::class,
            'provedor' => ProvedorIa::class,
            'iniciada_em' => 'datetime',
            'conferido_em' => 'datetime',
        ];
    }

    /** @return BelongsTo<Inscricao, $this> */
    public function inscricao(): BelongsTo
    {
        return $this->belongsTo(Inscricao::class);
    }

    /** @return BelongsTo<EditalDocumento, $this> */
    public function documento(): BelongsTo
    {
        return $this->belongsTo(EditalDocumento::class, 'edital_documento_id');
    }

    /** A situação para a tela: "conferindo" há mais de 5 minutos é mostrada como não conferida. */
    public function situacaoExibida(?CarbonInterface $agora = null): SituacaoConferencia
    {
        if ($this->situacao === SituacaoConferencia::Conferindo
            && $this->iniciada_em->lt(($agora ?? now())->copy()->subMinutes(self::MINUTOS_LIMITE))) {
            return SituacaoConferencia::NaoConferido;
        }

        return $this->situacao;
    }

    /** Só o documento ainda na conferência $execucao. */
    public function scopeDaExecucao(Builder $consulta, string $execucao): void
    {
        $consulta->where('execucao', $execucao)
            ->where('situacao', SituacaoConferencia::Conferindo->value);
    }
}
