<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edital_categoria_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('edital_categoria_id')->constrained('edital_categorias')->cascadeOnDelete();
            $table->string('nome', 255);
            $table->bigInteger('valor_centavos')->nullable();
            $table->integer('vagas')->nullable();
            $table->integer('suplentes')->nullable();
            $table->integer('ordem')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edital_categoria_itens');
    }
};
