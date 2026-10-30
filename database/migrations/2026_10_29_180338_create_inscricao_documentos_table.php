<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inscricao_documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inscricao_id')->constrained('inscricoes')->cascadeOnDelete();
            $table->foreignId('edital_documento_id')->constrained('edital_documentos')->cascadeOnDelete();
            $table->string('arquivo', 255);
            $table->string('arquivo_nome', 255);
            $table->string('situacao', 20);
            $table->string('motivo', 1000)->nullable();
            $table->string('erro', 1000)->nullable();
            $table->string('provedor', 20)->nullable();
            $table->string('modelo', 100)->nullable();
            $table->string('execucao', 36)->nullable();
            $table->dateTime('iniciada_em')->nullable();
            $table->dateTime('conferido_em')->nullable();
            $table->timestamps();

            $table->unique(['inscricao_id', 'edital_documento_id'], 'inscricao_docs_unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inscricao_documentos');
    }
};
