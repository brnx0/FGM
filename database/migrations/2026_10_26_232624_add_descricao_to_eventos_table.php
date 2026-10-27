<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Descrição em HTML já sanitizado (App\Support\HtmlSeguro); no Oracle vira CLOB. */
    public function up(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->longText('descricao')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->dropColumn('descricao');
        });
    }
};
