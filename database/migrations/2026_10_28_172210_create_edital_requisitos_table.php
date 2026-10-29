<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edital_requisitos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('edital_id')->constrained('editais')->cascadeOnDelete();
            $table->string('descricao', 1000);
            $table->integer('ordem')->default(0);
            $table->timestamps();
        });

        Schema::create('edital_requisito_documento', function (Blueprint $table) {
            $table->foreignId('edital_requisito_id')->constrained('edital_requisitos')->cascadeOnDelete();
            $table->foreignId('edital_documento_id')->constrained('edital_documentos')->cascadeOnDelete();
            $table->primary(['edital_requisito_id', 'edital_documento_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edital_requisito_documento');
        Schema::dropIfExists('edital_requisitos');
    }
};
