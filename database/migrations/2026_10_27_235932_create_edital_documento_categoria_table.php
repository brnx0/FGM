<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Categorias às quais um documento se aplica (quando não vale para todas). */
    public function up(): void
    {
        Schema::create('edital_documento_categoria', function (Blueprint $table) {
            $table->foreignId('edital_documento_id')->constrained('edital_documentos')->cascadeOnDelete();
            $table->foreignId('edital_categoria_id')->constrained('edital_categorias')->cascadeOnDelete();
            $table->unique(['edital_documento_id', 'edital_categoria_id'], 'edital_doc_cat_unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edital_documento_categoria');
    }
};
