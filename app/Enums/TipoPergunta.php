<?php

namespace App\Enums;

/** Tipo de resposta de uma pergunta do questionário do edital. */
enum TipoPergunta: string
{
    case TextoCurto = 'texto_curto';
    case TextoLongo = 'texto_longo';
    case EscolhaUnica = 'escolha_unica';
    case MultiplaEscolha = 'multipla_escolha';
    case SimNao = 'sim_nao';
    case Data = 'data';
    case Numero = 'numero';
    case Declaracao = 'declaracao';

    public const string CHAVE_SIM = 'sim';

    public const string CHAVE_NAO = 'nao';

    public const string CHAVE_DECLARACAO = 'declaro';

    public function rotulo(): string
    {
        return match ($this) {
            self::TextoCurto => 'Texto curto',
            self::TextoLongo => 'Texto longo',
            self::EscolhaUnica => 'Escolha única',
            self::MultiplaEscolha => 'Múltipla escolha',
            self::SimNao => 'Sim/não',
            self::Data => 'Data',
            self::Numero => 'Número',
            self::Declaracao => 'Declaração',
        };
    }

    /** Tem lista de opções cadastrada pelo gestor. */
    public function temOpcoes(): bool
    {
        return $this === self::EscolhaUnica || $this === self::MultiplaEscolha;
    }

    /** Outra pergunta pode depender da resposta a esta. */
    public function podeSerOrigem(): bool
    {
        return $this->temOpcoes() || $this === self::SimNao;
    }

    public function temLimite(): bool
    {
        return $this === self::TextoCurto || $this === self::TextoLongo;
    }

    public function limitePadrao(): ?int
    {
        return match ($this) {
            self::TextoCurto => 255,
            self::TextoLongo => 20000,
            default => null,
        };
    }
}
