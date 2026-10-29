<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edital_categorias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('edital_id')->constrained('editais')->cascadeOnDelete();
            $table->foreignId('edital_chamada_id')->nullable()->constrained('edital_chamadas')->nullOnDelete();
            $table->string('nome', 255);
            $table->string('descricao', 2000)->nullable();
            $table->bigInteger('valor_centavos')->nullable();
            $table->integer('vagas')->nullable();
            $table->string('naturezas', 200)->nullable();
            $table->integer('ordem')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edital_categorias');
    }
};
