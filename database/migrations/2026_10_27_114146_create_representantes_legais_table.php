<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('representantes_legais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizacao_id')->constrained('organizacoes')->cascadeOnDelete();
            $table->unsignedInteger('ordem');
            $table->string('cpf', 11);
            $table->string('nome');
            $table->string('tipo_documento')->nullable();
            $table->string('numero_documento', 30)->nullable();
            $table->string('cargo', 120)->nullable();
            $table->string('telefone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('origem')->nullable();
            $table->string('origem_descricao', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('representantes_legais');
    }
};
