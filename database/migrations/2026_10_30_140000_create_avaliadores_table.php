<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Avaliadores cadastrados na retaguarda. CPF guardado só com os 11 dígitos e e-mail em minúsculas, únicos entre eles.
     */
    public function up(): void
    {
        Schema::create('avaliadores', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 120);
            $table->date('data_nascimento');
            $table->string('cpf', 11)->unique('avaliadores_cpf_uk');
            $table->string('email', 255)->unique('avaliadores_email_uk');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avaliadores');
    }
};
