<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pessoas_fisicas', function (Blueprint $table) {
            // Perfil exigido pelos editais; cadastros antigos ficam sem ele até a pessoa completar.
            $table->string('deficiencia', 30)->nullable();
            $table->string('deficiencia_outra', 255)->nullable();
            $table->string('escolaridade', 30)->nullable();
            $table->string('renda', 20)->nullable();
            $table->string('povo_tradicional', 40)->nullable();
            $table->integer('anos_atuacao')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pessoas_fisicas', function (Blueprint $table) {
            $table->dropColumn(['deficiencia', 'deficiencia_outra', 'escolaridade', 'renda', 'povo_tradicional', 'anos_atuacao']);
        });
    }
};
