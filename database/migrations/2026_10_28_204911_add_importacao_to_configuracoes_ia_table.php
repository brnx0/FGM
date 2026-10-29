<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Parâmetros da importação de edital por IA, na configuração da Integração com IA: o prompt editável (nulo = o
     * prompt modelo do código), a temperatura e o tamanho máximo do PDF.
     */
    public function up(): void
    {
        Schema::table('configuracoes_ia', function (Blueprint $table) {
            $table->longText('importacao_prompt')->nullable();
            $table->decimal('importacao_temperatura', 3, 2)->default(0.10);
            $table->integer('importacao_tamanho_maximo_mb')->default(14);
        });
    }

    public function down(): void
    {
        Schema::table('configuracoes_ia', function (Blueprint $table) {
            $table->dropColumn(['importacao_prompt', 'importacao_temperatura', 'importacao_tamanho_maximo_mb']);
        });
    }
};
