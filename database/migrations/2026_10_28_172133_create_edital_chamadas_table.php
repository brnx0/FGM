<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edital_chamadas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('edital_id')->constrained('editais')->cascadeOnDelete();
            $table->string('nome', 100);
            // Nulas no rascunho (ex.: importação sem as datas); publicar exige ao menos uma chamada com as duas.
            $table->date('inicio')->nullable();
            $table->date('fim')->nullable();
            $table->integer('ordem')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edital_chamadas');
    }
};
