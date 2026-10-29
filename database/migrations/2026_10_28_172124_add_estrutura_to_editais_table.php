<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Campos do edital usados pela inscrição, validação e homologação; o rascunho pode ficar sem título, categoria, resumo e datas. */
return new class extends Migration
{
    /** @var list<string> */
    private const array PASSAM_A_ACEITAR_NULO = ['titulo', 'categoria', 'resumo', 'inscricoes_inicio', 'inscricoes_fim'];

    public function up(): void
    {
        foreach (self::PASSAM_A_ACEITAR_NULO as $coluna) {
            $this->definirNulo($coluna, true);
        }

        Schema::table('editais', function (Blueprint $table) {
            $table->string('numero', 30)->nullable();
            $table->string('tipo', 20)->nullable();
            $table->string('objeto', 1000)->nullable();
            $table->date('dom_data')->nullable();
            $table->string('dom_numero', 20)->nullable();
            $table->string('naturezas', 200)->nullable();
            $table->boolean('aceita_coletivos')->nullable();
            $table->integer('inscricoes_por_proponente')->nullable();
            $table->boolean('limite_por_chamada')->nullable();
            $table->longText('cotas')->nullable();
            $table->integer('nota_maxima')->nullable();
            $table->decimal('nota_corte', 5, 2)->nullable();
            $table->longText('pontuacao_extra')->nullable();
            $table->longText('informacoes')->nullable();
            $table->string('forma_inscricao_original', 1000)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('editais', function (Blueprint $table) {
            $table->dropColumn([
                'numero', 'tipo', 'objeto', 'dom_data', 'dom_numero', 'naturezas', 'aceita_coletivos',
                'inscricoes_por_proponente', 'limite_por_chamada', 'cotas', 'nota_maxima', 'nota_corte',
                'pontuacao_extra', 'informacoes', 'forma_inscricao_original',
            ]);
        });

        foreach (self::PASSAM_A_ACEITAR_NULO as $coluna) {
            $this->definirNulo($coluna, false);
        }
    }

    /**
     * Oracle: MODIFY só no que muda (repetir o estado atual dá ORA-01451/01442) e sem repetir o tipo (CLOB não aceita).
     * PostgreSQL: ALTER COLUMN, que aceita repetir o estado atual.
     */
    private function definirNulo(string $coluna, bool $aceitaNulo): void
    {
        if (DB::getDriverName() !== 'oracle') {
            DB::statement(sprintf('alter table editais alter column %s %s not null', $coluna, $aceitaNulo ? 'drop' : 'set'));

            return;
        }

        $atual = DB::selectOne(
            'select nullable from user_tab_columns where table_name = ? and column_name = ?',
            ['EDITAIS', strtoupper($coluna)],
        );

        if (($atual->nullable === 'Y') === $aceitaNulo) {
            return;
        }

        DB::statement(sprintf('alter table editais modify (%s %s)', $coluna, $aceitaNulo ? 'null' : 'not null'));
    }
};
