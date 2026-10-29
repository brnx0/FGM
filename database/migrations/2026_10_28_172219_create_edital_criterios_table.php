<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edital_criterios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('edital_id')->constrained('editais')->cascadeOnDelete();
            $table->string('nome', 500);
            $table->string('descricao', 2000)->nullable();
            $table->decimal('peso', 6, 2)->nullable();
            $table->decimal('pontos_maximos', 6, 2)->nullable();
            $table->boolean('todas_categorias')->default(true);
            $table->integer('ordem')->default(0);
            $table->timestamps();
        });

        Schema::create('edital_criterio_categoria', function (Blueprint $table) {
            $table->foreignId('edital_criterio_id')->constrained('edital_criterios')->cascadeOnDelete();
            $table->foreignId('edital_categoria_id')->constrained('edital_categorias')->cascadeOnDelete();
            $table->primary(['edital_criterio_id', 'edital_categoria_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edital_criterio_categoria');
        Schema::dropIfExists('edital_criterios');
    }
};
