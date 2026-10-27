<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizacoes', function (Blueprint $table) {
            $table->id();
            $table->string('cnpj', 14)->unique();
            $table->string('razao_social');
            $table->string('nome_fantasia')->nullable();
            $table->string('natureza_juridica', 4);
            $table->boolean('mei')->default(false);
            $table->boolean('finalidade_lucrativa')->nullable();
            $table->string('municipio_ibge', 7);
            $table->string('inscricao_municipal', 30)->nullable();
            $table->string('inscricao_estadual', 30)->nullable();
            $table->string('porte')->nullable();
            $table->date('data_fundacao')->nullable();
            $table->string('telefone', 20)->nullable();
            $table->string('logo')->nullable();
            $table->string('cep', 8)->nullable();
            $table->string('logradouro')->nullable();
            $table->string('numero', 20)->nullable();
            $table->boolean('sem_numero')->default(false);
            $table->string('complemento', 120)->nullable();
            $table->string('bairro', 120)->nullable();
            $table->string('cidade', 120)->nullable();
            $table->string('uf', 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizacoes');
    }
};
