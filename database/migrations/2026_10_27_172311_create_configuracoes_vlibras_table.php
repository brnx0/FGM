<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracoes_vlibras', function (Blueprint $table) {
            $table->id();
            $table->boolean('ativo')->default(true);
            // Valores de App\Enums\LadoVLibras e App\Enums\AvatarVLibras (os do próprio plugin).
            $table->string('lado', 1)->default('R');
            $table->string('avatar', 10)->default('icaro');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracoes_vlibras');
    }
};
