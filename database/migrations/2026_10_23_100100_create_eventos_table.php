<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eventos', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->string('slug')->unique();
            $table->text('resumo');
            $table->date('data_realizacao');
            $table->string('local')->nullable();
            $table->string('imagem')->nullable();
            $table->boolean('publicado')->default(true);
            $table->timestamps();

            $table->index(['publicado', 'data_realizacao']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos');
    }
};
