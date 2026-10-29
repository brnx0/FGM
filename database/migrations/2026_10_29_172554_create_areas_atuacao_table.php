<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Áreas de atuação do levantamento de categorias e eixos dos editais da FGM (31, com "Outros"). Os dados vão na
     * migration, e não num seeder, pelo mesmo motivo das Prefeituras-Bairro: a lista vale desde o primeiro acesso e uma
     * área desativada na retaguarda não pode voltar a cada implantação.
     *
     * @var list<string>
     */
    private const array AREAS = [
        'Arquitetura',
        'Artes Cênicas',
        'Artes Plásticas',
        'Artes Visuais',
        'Artesanato',
        'Atividades Comerciais',
        'Atividades de Entretenimento',
        'Atividades de Serviço',
        'Atividades de Turismo',
        'Atividades Industriais',
        'Audiovisual',
        'Cinema',
        'Dança e Coreografia',
        'Design Gráfico',
        'Escrita e linguagens',
        'Fotografia',
        'Impressão gráfica, edição e afins',
        'Livros',
        'Moda e vestuário',
        'Mídias Interativas',
        'Música',
        'Outras Atividades Artísticas',
        'Patrimônio',
        'Periódicos',
        'Produção Literária',
        'Publicidade',
        'Serviços Criativos de Base Cultural',
        'Serviços Culturais / Criativos',
        'Teatro',
        'Tecnologia da Informação',
        'Outros',
    ];

    public function up(): void
    {
        Schema::create('areas_atuacao', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 100)->unique('areas_atuacao_nome_uk');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        $agora = now();

        foreach (self::AREAS as $nome) {
            DB::table('areas_atuacao')->insert(['nome' => $nome, 'ativo' => true, 'created_at' => $agora, 'updated_at' => $agora]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('areas_atuacao');
    }
};
