<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cadastro de proponente pessoa física. Nome e CPF ficam em users (vêm do gov.br);
     * as colunas de arquivo guardam o caminho no disco local (privado).
     */
    public function up(): void
    {
        Schema::create('pessoas_fisicas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('nome_social')->nullable();
            $table->string('nome_artistico')->nullable();
            $table->string('foto_perfil')->nullable();

            $table->string('pais_origem', 60);
            $table->string('rg', 20);
            $table->string('documento_cpf');
            $table->string('documento_rg');
            $table->string('documento_passaporte')->nullable();
            $table->string('documento_crnm')->nullable();
            $table->string('documento_outros')->nullable();

            $table->string('drt', 30)->nullable();
            $table->boolean('cultura_principal_renda')->default(false);
            $table->boolean('ja_foi_avaliador')->default(false);
            $table->boolean('interesse_avaliador')->default(false);
            $table->boolean('representa_coletivo')->default(false);

            $table->string('telefone', 20);
            $table->string('telefone_adicional', 20)->nullable();
            $table->string('email');

            $table->string('identidade_genero', 30);
            $table->string('raca_cor', 30);
            $table->boolean('pcd')->default(false);

            $table->char('cep', 8);
            $table->string('logradouro');
            $table->string('numero', 20)->nullable();
            $table->string('bairro', 120);
            $table->string('complemento', 120)->nullable();
            $table->string('cidade', 120);
            $table->char('estado', 2);
            $table->string('territorio', 30);
            $table->string('comprovante_residencia')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pessoas_fisicas');
    }
};
