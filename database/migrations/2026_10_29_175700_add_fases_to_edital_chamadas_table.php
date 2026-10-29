<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edital_chamadas', function (Blueprint $table) {
            $table->date('validacao_inicio')->nullable();
            $table->date('validacao_fim')->nullable();
            $table->date('homologacao_inicio')->nullable();
            $table->date('homologacao_fim')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('edital_chamadas', function (Blueprint $table) {
            $table->dropColumn(['validacao_inicio', 'validacao_fim', 'homologacao_inicio', 'homologacao_fim']);
        });
    }
};
