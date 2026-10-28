<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Horário (HH:MM em texto: o Oracle não tem tipo de hora, e a data já fica à parte como dia civil),
     * endereço e as coordenadas do pin marcado no mapa pela retaguarda.
     */
    public function up(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->string('hora_inicio', 5)->nullable();
            $table->string('hora_fim', 5)->nullable();
            $table->string('endereco')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->dropColumn(['hora_inicio', 'hora_fim', 'endereco', 'latitude', 'longitude']);
        });
    }
};
