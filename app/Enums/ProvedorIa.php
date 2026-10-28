<?php

namespace App\Enums;

/** Provedor de IA das validações automáticas (Retaguarda > Parametrização > Integração com IA). */
enum ProvedorIa: string
{
    case OpenAi = 'openai';
    case Gemini = 'gemini';

    public function rotulo(): string
    {
        return match ($this) {
            self::OpenAi => 'OpenAI',
            self::Gemini => 'Google Gemini',
        };
    }

    /** Onde o gestor cria a chave; aparece embaixo do campo enquanto o provedor não tem uma guardada. */
    public function ajudaChave(): string
    {
        return match ($this) {
            self::OpenAi => 'Crie a chave em platform.openai.com/api-keys.',
            self::Gemini => 'Crie a chave em aistudio.google.com/apikey, num projeto com faturamento ativo: no plano gratuito, o Google pode usar o que for enviado.',
        };
    }
}
