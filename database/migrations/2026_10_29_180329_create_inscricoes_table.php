<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inscricoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('edital_id')->constrained('editais');
            $table->foreignId('edital_chamada_id')->nullable()->constrained('edital_chamadas')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('organizacao_id')->nullable()->constrained('organizacoes');
            // CPF ou CNPJ do proponente, só dígitos.
            $table->string('documento_cadastro', 14);
            $table->string('natureza', 20);
            $table->foreignId('edital_categoria_id')->nullable()->constrained('edital_categorias')->nullOnDelete();
            // JSON: lista de ModalidadeCota.
            $table->string('cotas', 100)->nullable();
            $table->string('situacao', 20);
            $table->string('numero', 20)->nullable()->unique();
            $table->dateTime('finalizada_em')->nullable();
            $table->timestamps();

            $table->index(['edital_id', 'documento_cadastro'], 'inscricoes_edital_doc_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inscricoes');
    }
};
