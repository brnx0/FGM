<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Cada edital já cadastrado (excluídos inclusive) ganha a chamada "Inscrições" com as datas que já tem; nada mais muda nele. */
return new class extends Migration
{
    public function up(): void
    {
        $agora = now();

        DB::table('editais')
            ->whereNotExists(fn (Builder $q) => $q->select(DB::raw(1))
                ->from('edital_chamadas')
                ->whereColumn('edital_chamadas.edital_id', 'editais.id'))
            ->orderBy('id')
            ->get(['id', 'inscricoes_inicio', 'inscricoes_fim'])
            ->each(fn (object $edital) => DB::table('edital_chamadas')->insert([
                'edital_id' => $edital->id,
                'nome' => 'Inscrições',
                'inicio' => $edital->inscricoes_inicio,
                'fim' => $edital->inscricoes_fim,
                'ordem' => 1,
                'created_at' => $agora,
                'updated_at' => $agora,
            ]));
    }

    /** As chamadas criadas aqui saem com a tabela (create_edital_chamadas_table). */
    public function down(): void {}
};
