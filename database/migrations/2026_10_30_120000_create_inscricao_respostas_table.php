<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inscricao_respostas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inscricao_id')->constrained('inscricoes')->cascadeOnDelete();
            $table->foreignId('edital_pergunta_id')->constrained('edital_perguntas')->cascadeOnDelete();
            // Textos, data (Y-m-d) e número; CLOB no Oracle: não entra em listagens.
            $table->longText('texto')->nullable();
            // JSON: lista de chaves das opções escolhidas (sim/nao e declaro também).
            $table->string('opcoes', 2000)->nullable();
            // O "Qual?" da opção "Outro".
            $table->string('outro', 1000)->nullable();
            $table->timestamps();

            $table->unique(['inscricao_id', 'edital_pergunta_id'], 'inscricao_respostas_unica');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inscricao_respostas');
    }
};
