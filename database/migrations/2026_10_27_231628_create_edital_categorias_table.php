<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Categorias de inscrição do edital, com valor, vagas, naturezas aceitas e reserva de cotas. */
    public function up(): void
    {
        Schema::create('edital_categorias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('edital_id')->constrained('editais')->cascadeOnDelete();
            $table->unsignedInteger('ordem');
            $table->string('nome');
            $table->bigInteger('valor_centavos');
            $table->integer('vagas');
            // JSON com os valores de NaturezaProponente (como users.tipos_cadastro).
            $table->string('naturezas', 200);
            foreach (['negros', 'indigenas', 'pcd'] as $cota) {
                $table->integer("cota_{$cota}_valor")->nullable();
                $table->string("cota_{$cota}_unidade", 10)->nullable();
            }
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edital_categorias');
    }
};
