<?php

use App\Support\ResumoEmHtml;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** O resumo vira a divulgação (HTML) e a vigência começa no início das inscrições. */
    public function up(): void
    {
        Schema::table('editais', function (Blueprint $table) {
            $table->string('tipo_fomento', 10)->nullable();
            $table->date('vigencia_inicio')->nullable();
            $table->date('vigencia_fim')->nullable();
            $table->longText('divulgacao')->nullable();
        });

        // Inclui os excluídos (a consulta do query builder não aplica o escopo de exclusão lógica).
        foreach (DB::table('editais')->select(['id', 'resumo', 'inscricoes_inicio'])->get() as $edital) {
            DB::table('editais')->where('id', $edital->id)->update([
                'divulgacao' => ResumoEmHtml::converter($edital->resumo),
                'vigencia_inicio' => $edital->inscricoes_inicio,
            ]);
        }

        Schema::table('editais', function (Blueprint $table) {
            $table->dropColumn('resumo');
        });
    }

    public function down(): void
    {
        Schema::table('editais', function (Blueprint $table) {
            $table->text('resumo')->nullable();
        });

        foreach (DB::table('editais')->select(['id', 'divulgacao'])->get() as $edital) {
            DB::table('editais')->where('id', $edital->id)->update([
                'resumo' => $edital->divulgacao === null ? null : trim(html_entity_decode(strip_tags($edital->divulgacao))),
            ]);
        }

        Schema::table('editais', function (Blueprint $table) {
            $table->dropColumn(['tipo_fomento', 'vigencia_inicio', 'vigencia_fim', 'divulgacao']);
        });
    }
};
