<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizacao_vinculos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizacao_id')->constrained('organizacoes')->cascadeOnDelete();
            $table->string('cpf', 11)->index();
            $table->string('nome');
            $table->boolean('responsavel')->default(false);
            $table->timestamps();

            $table->unique(['organizacao_id', 'cpf']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizacao_vinculos');
    }
};
