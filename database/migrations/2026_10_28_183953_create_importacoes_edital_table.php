<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('importacoes_edital', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            // Excluir o edital criado não apaga a importação.
            $table->foreignId('edital_id')->nullable()->constrained('editais')->nullOnDelete();
            // App\Enums\SituacaoImportacao.
            $table->string('situacao', 20);
            // PDF enviado, no disco local (privado); fica mesmo se a análise falhar.
            $table->string('pdf', 255);
            $table->string('pdf_nome', 255);
            // Provedor (App\Enums\ProvedorIa) e modelo da Integração com IA que responderam.
            $table->string('provedor', 20)->nullable();
            $table->string('modelo', 100);
            $table->integer('tokens_entrada')->nullable();
            $table->integer('tokens_saida')->nullable();
            $table->integer('tokens_total')->nullable();
            // Só mensagens em português para a equipe; nunca a chave da API.
            $table->string('erro', 1000)->nullable();
            // JSON recebido da IA, confiabilidade e pontos de atenção (CLOB, fora das listagens).
            $table->longText('resposta')->nullable();
            $table->longText('confiabilidade')->nullable();
            $table->longText('atencao')->nullable();
            $table->timestamp('conferida_em')->nullable();
            $table->timestamp('iniciada_em');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('importacoes_edital');
    }
};
