<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Lista de App\Enums\TipoCadastro em JSON (um tipo ou os dois); nula enquanto o cadastro não é finalizado.
            $table->string('tipos_cadastro', 40)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('tipos_cadastro');
        });
    }
};
