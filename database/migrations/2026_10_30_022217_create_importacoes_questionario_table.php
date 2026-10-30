<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('importacoes_questionario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            // Excluir o edital apaga as importações do questionário dele.
            $table->foreignId('edital_id')->constrained('editais')->cascadeOnDelete();
            // App\Enums\SituacaoImportacao.
            $table->string('situacao', 20);
            // Arquivo enviado (PDF ou Word .docx), no disco local (privado); fica mesmo se a análise falhar.
            $table->string('arquivo', 255);
            $table->string('arquivo_nome', 255);
            // pdf ou docx.
            $table->string('tipo_arquivo', 10);
            // Quem enviou confirmou substituir o questionário existente; sem isso, a análise não apaga seções.
            $table->boolean('substituir')->default(false);
            // Provedor (App\Enums\ProvedorIa) e modelo da Integração com IA que responderam.
            $table->string('provedor', 20)->nullable();
            $table->string('modelo', 100);
            $table->integer('tokens_entrada')->nullable();
            $table->integer('tokens_saida')->nullable();
            $table->integer('tokens_total')->nullable();
            // Só mensagens em português para a equipe; nunca a chave da API.
            $table->string('erro', 1000)->nullable();
            // JSON recebido da IA, confiabilidade e pontos de atenção (CLOB, fora das consultas repetidas).
            $table->longText('resposta')->nullable();
            $table->longText('confiabilidade')->nullable();
            $table->longText('atencao')->nullable();
            $table->timestamp('conferida_em')->nullable();
            $table->timestamp('iniciada_em');
            // Identifica a análise em curso: só o processo lançado com este valor pode concluir ou dar erro.
            $table->string('execucao', 36)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('importacoes_questionario');
    }
};
