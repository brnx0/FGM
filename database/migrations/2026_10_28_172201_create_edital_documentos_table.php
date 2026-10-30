<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edital_documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('edital_id')->constrained('editais')->cascadeOnDelete();
            $table->string('nome', 255);
            $table->string('orientacao', 1000)->nullable();
            $table->string('etapa', 20);
            $table->boolean('obrigatorio')->default(true);
            // Nulo = todas as naturezas do edital.
            $table->string('naturezas', 200)->nullable();
            $table->boolean('todas_categorias')->default(true);
            $table->string('modalidade_cota', 20)->nullable();
            $table->string('modelo', 255)->nullable();
            $table->string('modelo_nome', 255)->nullable();
            $table->integer('ordem')->default(0);
            $table->timestamps();
        });

        Schema::create('edital_documento_categoria', function (Blueprint $table) {
            $table->foreignId('edital_documento_id')->constrained('edital_documentos')->cascadeOnDelete();
            $table->foreignId('edital_categoria_id')->constrained('edital_categorias')->cascadeOnDelete();
            $table->primary(['edital_documento_id', 'edital_categoria_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edital_documento_categoria');
        Schema::dropIfExists('edital_documentos');
    }
};
