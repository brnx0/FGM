<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inscricoes', function (Blueprint $table) {
            // JSON: retrato do perfil da pessoa física dona da inscrição, refeito a cada finalização; CLOB no Oracle.
            $table->longText('perfil')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('inscricoes', function (Blueprint $table) {
            $table->dropColumn('perfil');
        });
    }
};
