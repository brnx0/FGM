<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Migration que desfaz os ajustes antigos do edital num banco que já os rodou (como o vindo da master).
 *
 * Roda numa conexão SQLite em memória: no Oracle de testes, simular as tabelas antigas exigiria apagar as novas, de
 * mesmo nome, num banco compartilhado (e o DDL do Oracle confirma a transação do teste). O caminho no Oracle é
 * conferido com o migrate do banco de desenvolvimento.
 */
class LimpezaAjustesAntigosTest extends TestCase
{
    private const string MIGRATION = 'migrations/2026_10_28_172000_limpar_ajustes_antigos_do_edital.php';

    private const array MIGRATIONS_ANTIGAS = [
        '2026_10_27_225850_add_dados_gerais_to_editais_table',
        '2026_10_27_231628_create_edital_categorias_table',
        '2026_10_27_235924_create_edital_documentos_table',
        '2026_10_27_235932_create_edital_documento_categoria_table',
    ];

    private const string OUTRA_MIGRATION = '2026_10_28_170128_create_integracao_ia_tables';

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.limpeza' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]]);
        DB::setDefaultConnection('limpeza');

        Schema::create('migrations', function (Blueprint $table) {
            $table->id();
            $table->string('migration');
            $table->integer('batch');
        });
        DB::table('migrations')->insert(['migration' => self::OUTRA_MIGRATION, 'batch' => 1]);

        Schema::create('editais', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->softDeletes();
        });
    }

    private function limpar(): void
    {
        (require database_path(self::MIGRATION))->up();
    }

    /** Como ficou o banco depois das 4 migrations antigas: sem resumo, com a divulgação em HTML. */
    private function criarBancoAntigo(): void
    {
        Schema::table('editais', function (Blueprint $table) {
            $table->string('tipo_fomento', 10)->nullable();
            $table->date('vigencia_inicio')->nullable();
            $table->date('vigencia_fim')->nullable();
            $table->longText('divulgacao')->nullable();
        });

        Schema::create('edital_categorias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('edital_id')->constrained('editais')->cascadeOnDelete();
            $table->unsignedInteger('ordem');
            $table->string('nome');
            $table->bigInteger('valor_centavos');
            $table->integer('vagas');
            $table->string('naturezas', 200);
            foreach (['negros', 'indigenas', 'pcd'] as $cota) {
                $table->integer("cota_{$cota}_valor")->nullable();
                $table->string("cota_{$cota}_unidade", 10)->nullable();
            }
            $table->timestamps();
        });

        Schema::create('edital_documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('edital_id')->constrained('editais')->cascadeOnDelete();
            $table->unsignedInteger('ordem');
            $table->string('nome');
            $table->string('naturezas', 200);
            $table->boolean('todas_categorias')->default(true);
            $table->integer('tamanho_max_mb')->default(2);
            $table->timestamps();
        });

        Schema::create('edital_documento_categoria', function (Blueprint $table) {
            $table->foreignId('edital_documento_id')->constrained('edital_documentos')->cascadeOnDelete();
            $table->foreignId('edital_categoria_id')->constrained('edital_categorias')->cascadeOnDelete();
            $table->unique(['edital_documento_id', 'edital_categoria_id'], 'edital_doc_cat_unico');
        });

        foreach (self::MIGRATIONS_ANTIGAS as $migration) {
            DB::table('migrations')->insert(['migration' => $migration, 'batch' => 2]);
        }

        DB::table('editais')->insert([
            ['id' => 1, 'titulo' => 'Salvador Circula', 'divulgacao' => '<p>Edital &amp; cultura &quot;viva&quot; na cidade.</p>', 'deleted_at' => null],
            ['id' => 2, 'titulo' => 'Sem divulgação', 'divulgacao' => null, 'deleted_at' => null],
            ['id' => 3, 'titulo' => 'Excluído', 'divulgacao' => '<p>Texto do excluído.</p>', 'deleted_at' => now()],
        ]);
        DB::table('edital_categorias')->insert(['id' => 1, 'edital_id' => 1, 'ordem' => 1, 'nome' => 'Teatro', 'valor_centavos' => 100, 'vagas' => 1, 'naturezas' => '[]', 'cota_negros_valor' => 25]);
        DB::table('edital_documentos')->insert(['id' => 1, 'edital_id' => 1, 'ordem' => 1, 'nome' => 'Portfólio', 'naturezas' => '[]', 'tamanho_max_mb' => 2]);
        DB::table('edital_documento_categoria')->insert(['edital_documento_id' => 1, 'edital_categoria_id' => 1]);
    }

    /** Banco que nunca rodou os ajustes antigos: resumo e a estrutura nova do edital. */
    private function criarBancoNovo(): void
    {
        Schema::table('editais', function (Blueprint $table) {
            $table->text('resumo')->nullable();
        });

        Schema::create('edital_categorias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('edital_id')->constrained('editais')->cascadeOnDelete();
            $table->string('nome', 255);
            $table->integer('ordem')->default(0);
        });

        Schema::create('edital_documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('edital_id')->constrained('editais')->cascadeOnDelete();
            $table->string('nome', 255);
            $table->string('etapa', 20);
            $table->integer('ordem')->default(0);
        });

        Schema::create('edital_documento_categoria', function (Blueprint $table) {
            $table->foreignId('edital_documento_id')->constrained('edital_documentos')->cascadeOnDelete();
            $table->foreignId('edital_categoria_id')->constrained('edital_categorias')->cascadeOnDelete();
            $table->primary(['edital_documento_id', 'edital_categoria_id']);
        });

        DB::table('editais')->insert(['id' => 1, 'titulo' => 'Salvador Circula', 'resumo' => 'Resumo atual.']);
        DB::table('edital_categorias')->insert(['id' => 1, 'edital_id' => 1, 'nome' => 'Teatro']);
        DB::table('edital_documentos')->insert(['id' => 1, 'edital_id' => 1, 'nome' => 'Portfólio', 'etapa' => 'inscricao']);
        DB::table('edital_documento_categoria')->insert(['edital_documento_id' => 1, 'edital_categoria_id' => 1]);
    }

    private function assertBancoLimpo(): void
    {
        $this->assertFalse(Schema::hasTable('edital_documento_categoria'));
        $this->assertFalse(Schema::hasTable('edital_documentos'));
        $this->assertFalse(Schema::hasTable('edital_categorias'));
        $this->assertSame(['id', 'titulo', 'deleted_at', 'resumo'], Schema::getColumnListing('editais'));
        $this->assertSame([
            1 => 'Edital & cultura "viva" na cidade.',
            2 => null,
            3 => 'Texto do excluído.',
        ], DB::table('editais')->orderBy('id')->pluck('resumo', 'id')->all());
        $this->assertSame([self::OUTRA_MIGRATION], DB::table('migrations')->pluck('migration')->all());
    }

    public function test_banco_com_os_ajustes_antigos_fica_sem_eles_e_com_o_resumo_em_texto(): void
    {
        $this->criarBancoAntigo();

        $this->limpar();

        $this->assertBancoLimpo();
    }

    public function test_rodar_de_novo_nao_falha_nem_muda_nada(): void
    {
        $this->criarBancoAntigo();
        $this->limpar();

        $this->limpar();

        $this->assertBancoLimpo();
    }

    public function test_banco_sem_os_ajustes_antigos_fica_como_esta(): void
    {
        $this->criarBancoNovo();
        $colunas = fn (): array => array_map(
            fn (string $tabela): array => Schema::getColumnListing($tabela),
            ['editais', 'edital_categorias', 'edital_documentos', 'edital_documento_categoria'],
        );
        $antes = $colunas();

        $this->limpar();

        $this->assertSame($antes, $colunas());
        $this->assertSame('Resumo atual.', DB::table('editais')->value('resumo'));
        $this->assertSame(1, DB::table('edital_categorias')->count());
        $this->assertSame(1, DB::table('edital_documentos')->count());
        $this->assertSame(1, DB::table('edital_documento_categoria')->count());
        $this->assertSame([self::OUTRA_MIGRATION], DB::table('migrations')->pluck('migration')->all());
    }
}
