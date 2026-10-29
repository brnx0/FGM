<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Migrations dos ajustes antigos do edital, que saíram do projeto. */
    private const array MIGRATIONS_ANTIGAS = [
        '2026_10_27_225850_add_dados_gerais_to_editais_table',
        '2026_10_27_231628_create_edital_categorias_table',
        '2026_10_27_235924_create_edital_documentos_table',
        '2026_10_27_235932_create_edital_documento_categoria_table',
    ];

    /** Colunas que os ajustes antigos puseram em editais. */
    private const array COLUNAS_ANTIGAS = ['tipo_fomento', 'vigencia_inicio', 'vigencia_fim', 'divulgacao'];

    /**
     * Desfaz os ajustes antigos do edital num banco que já os rodou (ex.: o vindo da master), para a estrutura nova
     * (2026_10_28_172124 em diante) subir só com migrate. Confere o formato antes de apagar: as tabelas antigas têm
     * colunas que as novas, de mesmo nome, não têm. Num banco que nunca os rodou não faz nada, e rodar de novo também não.
     */
    public function up(): void
    {
        if (Schema::hasTable('edital_documentos')
            && Schema::hasColumn('edital_documentos', 'tamanho_max_mb')
            && ! Schema::hasColumn('edital_documentos', 'etapa')) {
            // O pivô antigo liga os documentos e as categorias antigas.
            Schema::dropIfExists('edital_documento_categoria');
            Schema::drop('edital_documentos');
        }

        if (Schema::hasTable('edital_categorias') && Schema::hasColumn('edital_categorias', 'cota_negros_valor')) {
            Schema::drop('edital_categorias');
        }

        if (Schema::hasColumn('editais', 'divulgacao')) {
            $this->devolverResumo();
        }

        $colunasAntigas = array_values(array_filter(self::COLUNAS_ANTIGAS, fn (string $coluna): bool => Schema::hasColumn('editais', $coluna)));
        if ($colunasAntigas !== []) {
            Schema::table('editais', function (Blueprint $table) use ($colunasAntigas) {
                $table->dropColumn($colunasAntigas);
            });
        }

        DB::table(config('database.migrations.table'))->whereIn('migration', self::MIGRATIONS_ANTIGAS)->delete();
    }

    /**
     * Sem volta: os ajustes antigos foram abandonados e as migrations deles saíram do projeto.
     */
    public function down(): void {}

    /**
     * Recria o resumo em texto a partir da divulgação, que era o resumo antigo dentro de um parágrafo HTML. Inclui os
     * editais excluídos (o query builder não aplica o escopo da exclusão lógica).
     */
    private function devolverResumo(): void
    {
        if (! Schema::hasColumn('editais', 'resumo')) {
            Schema::table('editais', function (Blueprint $table) {
                $table->text('resumo')->nullable();
            });
        }

        foreach (DB::table('editais')->select(['id', 'divulgacao'])->orderBy('id')->get() as $edital) {
            $resumo = $edital->divulgacao === null ? '' : trim(html_entity_decode(strip_tags($edital->divulgacao)));

            DB::table('editais')->where('id', $edital->id)->update(['resumo' => $resumo === '' ? null : $resumo]);
        }
    }
};
