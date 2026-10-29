<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edital_documentos', function (Blueprint $table) {
            // Documentos já cadastrados e novos começam aceitando o envio sem a IA validar.
            $table->string('checagem', 20)->default('aceita_sem_validar');
            $table->string('instrucao_ia', 2000)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('edital_documentos', function (Blueprint $table) {
            $table->dropColumn(['checagem', 'instrucao_ia']);
        });
    }
};
