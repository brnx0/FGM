<?php

namespace Tests\Feature\Retaguarda;

use App\Models\Evento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EventosTest extends TestCase
{
    use RefreshDatabase;

    /** PNG de 1×1 pixel (o container não tem GD para UploadedFile::fake()->image()). */
    private const string PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->actingAs(User::factory()->gestor()->create());
    }

    private function imagem(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('capa.png', base64_decode(self::PNG));
    }

    /**
     * @param  array<string, mixed>  $alteracoes
     * @return array<string, mixed>
     */
    private function dados(array $alteracoes = []): array
    {
        return [
            'titulo' => 'Capoeira Viva',
            'resumo' => 'Rodas e oficinas de capoeira.',
            'data_realizacao' => '2026-09-06',
            'local' => 'Barroquinha',
            'publicado' => '0',
            ...$alteracoes,
        ];
    }

    public function test_lista_com_busca_e_filtro(): void
    {
        Evento::factory()->create(['titulo' => 'Capoeira Viva', 'data_realizacao' => '2026-09-06']);
        Evento::factory()->create(['titulo' => 'Dois de Julho', 'data_realizacao' => '2026-07-02', 'publicado' => false]);
        Evento::factory()->create(['titulo' => 'Capoeira excluída'])->delete();

        $this->get('/retaguarda/eventos')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('Retaguarda/Eventos/Index')
                ->has('eventos.data', 2)
                ->where('eventos.data.0.titulo', 'Capoeira Viva')
                ->where('eventos.data.0.data', '06/09/2026')
                ->where('eventos.per_page', 15));

        $this->get('/retaguarda/eventos?busca=CAPOEIRA')
            ->assertInertia(fn (Assert $p) => $p->has('eventos.data', 1)->where('filtros.busca', 'CAPOEIRA'));

        $this->get('/retaguarda/eventos?busca=%25')
            ->assertInertia(fn (Assert $p) => $p->has('eventos.data', 0));

        $this->get('/retaguarda/eventos?publicado=nao')
            ->assertInertia(fn (Assert $p) => $p->has('eventos.data', 1)->where('eventos.data.0.titulo', 'Dois de Julho'));

        $this->get('/retaguarda/eventos?publicado=sim')
            ->assertInertia(fn (Assert $p) => $p->has('eventos.data', 1)->where('eventos.data.0.titulo', 'Capoeira Viva'));
    }

    public function test_cria_evento_com_imagem(): void
    {
        $this->post('/retaguarda/eventos', $this->dados(['publicado' => '1', 'imagem' => $this->imagem()]))
            ->assertSessionHasNoErrors()
            ->assertRedirect('/retaguarda/eventos')
            ->assertSessionHas('sucesso', 'Evento salvo.');

        $evento = Evento::sole();
        $this->assertSame('capoeira-viva', $evento->slug);
        $this->assertTrue($evento->publicado);
        $this->assertSame('2026-09-06', $evento->data_realizacao->toDateString());
        $this->assertStringStartsWith('eventos/', $evento->imagem);
        Storage::disk('public')->assertExists($evento->imagem);
    }

    public function test_valida_campos_obrigatorios(): void
    {
        $this->post('/retaguarda/eventos', ['publicado' => '0'])
            ->assertSessionHasErrors(['titulo', 'resumo', 'data_realizacao']);

        $this->assertSame(0, Evento::count());
    }

    public function test_slug_unico_mesmo_com_excluido(): void
    {
        Evento::factory()->create(['titulo' => 'Capoeira Viva', 'slug' => 'capoeira-viva'])->delete();

        $this->post('/retaguarda/eventos', $this->dados())->assertSessionHasNoErrors();
        $this->post('/retaguarda/eventos', $this->dados())->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing(['capoeira-viva-2', 'capoeira-viva-3'], Evento::pluck('slug')->all());
    }

    public function test_editar_sem_reenviar_mantem_imagem(): void
    {
        $evento = Evento::factory()->create(['slug' => 'capoeira-viva', 'imagem' => 'eventos/atual.png']);
        Storage::disk('public')->put('eventos/atual.png', 'png');

        $this->put('/retaguarda/eventos/capoeira-viva', $this->dados(['titulo' => 'Título novo']))
            ->assertSessionHasNoErrors()
            ->assertRedirect('/retaguarda/eventos')
            ->assertSessionHas('sucesso', 'Evento salvo.');

        $evento->refresh();
        $this->assertSame('Título novo', $evento->titulo);
        $this->assertSame('capoeira-viva', $evento->slug);
        $this->assertSame('eventos/atual.png', $evento->imagem);
        Storage::disk('public')->assertExists('eventos/atual.png');
    }

    public function test_trocar_imagem_apaga_a_antiga(): void
    {
        $evento = Evento::factory()->create(['imagem' => 'eventos/antiga.png']);
        Storage::disk('public')->put('eventos/antiga.png', 'png');

        $this->put("/retaguarda/eventos/{$evento->slug}", $this->dados(['imagem' => $this->imagem()]))
            ->assertSessionHasNoErrors();

        $evento->refresh();
        $this->assertNotSame('eventos/antiga.png', $evento->imagem);
        Storage::disk('public')->assertMissing('eventos/antiga.png');
        Storage::disk('public')->assertExists($evento->imagem);
    }

    public function test_remover_imagem(): void
    {
        $evento = Evento::factory()->create(['imagem' => 'eventos/capa.png']);
        Storage::disk('public')->put('eventos/capa.png', 'png');

        $this->put("/retaguarda/eventos/{$evento->slug}", $this->dados(['remover_imagem' => '1']))
            ->assertSessionHasNoErrors();

        $this->assertNull($evento->refresh()->imagem);
        Storage::disk('public')->assertMissing('eventos/capa.png');
    }

    public function test_exclui_logicamente(): void
    {
        $evento = Evento::factory()->create(['imagem' => 'eventos/fica.png']);
        Storage::disk('public')->put('eventos/fica.png', 'png');

        $this->delete("/retaguarda/eventos/{$evento->slug}")
            ->assertRedirect('/retaguarda/eventos')
            ->assertSessionHas('sucesso', 'Evento excluído.');

        $this->assertSoftDeleted($evento);
        Storage::disk('public')->assertExists('eventos/fica.png');
    }

    public function test_alterna_publicacao(): void
    {
        $evento = Evento::factory()->create(['publicado' => false]);

        $this->patch("/retaguarda/eventos/{$evento->slug}/publicacao")->assertSessionHas('sucesso', 'Evento publicado.');
        $this->assertTrue($evento->refresh()->publicado);

        $this->patch("/retaguarda/eventos/{$evento->slug}/publicacao")->assertSessionHas('sucesso', 'Evento despublicado.');
        $this->assertFalse($evento->refresh()->publicado);
    }

    public function test_evento_criado_aparece_na_home(): void
    {
        $this->post('/retaguarda/eventos', $this->dados(['titulo' => 'Novo na home', 'publicado' => '1', 'data_realizacao' => '2025-05-10']))
            ->assertSessionHasNoErrors();

        $this->get('/')->assertInertia(fn (Assert $p) => $p->where('eventos.0.titulo', 'Novo na home'));
    }

    public function test_proponente_nao_acessa(): void
    {
        $evento = Evento::factory()->create();
        $this->actingAs(User::factory()->create());

        $this->get('/retaguarda/eventos')->assertRedirect('/retaguarda/login');
        $this->post('/retaguarda/eventos', $this->dados())->assertRedirect('/retaguarda/login');
        $this->delete("/retaguarda/eventos/{$evento->slug}")->assertRedirect('/retaguarda/login');

        $this->assertSame(1, Evento::count());
    }
}
