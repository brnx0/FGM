<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('importacoes_edital', function (Blueprint $table) {
            // Identifica a análise em curso: só o processo lançado com este valor pode concluir ou dar erro.
            $table->string('execucao', 36)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('importacoes_edital', function (Blueprint $table) {
            $table->dropColumn('execucao');
        });
    }
};
