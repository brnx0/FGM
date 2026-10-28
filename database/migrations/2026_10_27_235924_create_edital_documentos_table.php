<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Documentos exigidos na inscrição do edital (a retaguarda valida cada um depois). */
    public function up(): void
    {
        Schema::create('edital_documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('edital_id')->constrained('editais')->cascadeOnDelete();
            $table->unsignedInteger('ordem');
            $table->string('nome');
            $table->string('orientacao', 500)->nullable();
            $table->boolean('obrigatorio')->default(true);
            // JSON com os valores de NaturezaProponente (como edital_categorias.naturezas).
            $table->string('naturezas', 200);
            $table->boolean('todas_categorias')->default(true);
            $table->integer('tamanho_max_mb')->default(2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edital_documentos');
    }
};
