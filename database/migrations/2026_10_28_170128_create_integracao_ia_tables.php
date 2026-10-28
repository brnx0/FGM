<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Integração com IA (Retaguarda > Parametrização): uma conexão por provedor, com o modelo e a chave da API, e a
     * configuração de uma linha só, que diz se a IA está ligada, qual é o provedor principal e qual é a redundância.
     * Cada provedor guarda a própria chave, então trocar o principal não apaga nada.
     *
     * A chave vai cifrada com a APP_KEY (cast encrypted): uns 480 caracteres para uma chave de projeto da OpenAI,
     * por isso VARCHAR2(1000) e não CLOB.
     */
    public function up(): void
    {
        Schema::create('conexoes_ia', function (Blueprint $table) {
            $table->id();
            // Valor de App\Enums\ProvedorIa.
            $table->string('provedor', 20)->unique('conexoes_ia_provedor_uk');
            $table->string('modelo', 100)->nullable();
            $table->string('chave_api', 1000)->nullable();
            $table->timestamps();
        });

        Schema::create('configuracoes_ia', function (Blueprint $table) {
            $table->id();
            $table->boolean('ativo')->default(false);
            // Valores de App\Enums\ProvedorIa; a redundância nunca é o próprio principal.
            $table->string('provedor_principal', 20)->nullable();
            $table->string('provedor_redundancia', 20)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracoes_ia');
        Schema::dropIfExists('conexoes_ia');
    }
};
