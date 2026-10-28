<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Prefeituras-Bairro de Salvador e os bairros de cada uma, já com a delimitação oficial: database/data/prefeituras_bairro.json,
     * tirado da camada Limite_de_Bairros do GIS da Prefeitura (FMLF/Sedur) em 28/10/2026 — 10 Prefeituras-Bairro (Lei 8.376/2012)
     * e 171 bairros (Lei 9.278/2017, Decreto 32.791/2020 e Lei 9.778/2024). Os dados vão na migration, e não num seeder, porque o
     * portal depende deles desde o primeiro acesso e um bairro apagado na retaguarda não pode voltar a cada implantação.
     *
     * O código das Prefeituras-Bairro é o que pessoas_fisicas.territorio já guarda; não muda depois de criado.
     */
    public function up(): void
    {
        Schema::create('prefeituras_bairro', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('numero')->unique('pref_bairro_numero_uk');
            $table->string('nome', 60)->unique('pref_bairro_nome_uk');
            $table->string('codigo', 30)->unique('pref_bairro_codigo_uk');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('bairros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prefeitura_bairro_id')->constrained('prefeituras_bairro', indexName: 'bairros_pref_bairro_fk');
            // Um bairro pertence a uma só Prefeitura-Bairro.
            $table->string('nome', 80)->unique();
            $table->timestamps();
        });

        $agora = now();

        /** @var list<array{numero: int, codigo: string, nome: string, bairros: list<string>}> $prefeituras */
        $prefeituras = json_decode((string) file_get_contents(database_path('data/prefeituras_bairro.json')), true, flags: JSON_THROW_ON_ERROR);

        foreach ($prefeituras as $prefeitura) {
            DB::table('prefeituras_bairro')->insert([
                'numero' => $prefeitura['numero'],
                'nome' => $prefeitura['nome'],
                'codigo' => $prefeitura['codigo'],
                'ativo' => true,
                'created_at' => $agora,
                'updated_at' => $agora,
            ]);
            $id = DB::table('prefeituras_bairro')->where('codigo', $prefeitura['codigo'])->value('id');

            // Uma linha por vez: o INSERT ALL do Oracle não gera um id por linha na coluna de identidade.
            foreach ($prefeitura['bairros'] as $bairro) {
                DB::table('bairros')->insert([
                    'prefeitura_bairro_id' => $id,
                    'nome' => $bairro,
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bairros');
        Schema::dropIfExists('prefeituras_bairro');
    }
};
