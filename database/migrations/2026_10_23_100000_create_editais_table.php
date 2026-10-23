<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('editais', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->string('slug')->unique();
            $table->text('resumo');
            $table->string('categoria');
            $table->date('inscricoes_inicio');
            $table->date('inscricoes_fim');
            $table->string('imagem')->nullable();
            $table->boolean('publicado')->default(true);
            $table->timestamps();

            $table->index(['publicado', 'inscricoes_fim']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('editais');
    }
};
